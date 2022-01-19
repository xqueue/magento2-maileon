<?php
 
namespace Xqueue\Maileon\Model\Api;

use Magento\Store\Model\ScopeInterface;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;
 
class MaileonTestAbandonedCarts
{
    protected $logger;

    protected $_logFactory;

    protected $_queueFactory;

    protected $_quoteFactory;

    protected $_storeManager;

    protected $helper;

    protected $_messageManager;
 
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Xqueue\Maileon\Model\LogFactory $logFactory,
        \Xqueue\Maileon\Model\QueueFactory $queueFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Xqueue\Maileon\Helper\External\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->_logFactory = $logFactory;
        $this->_queueFactory = $queueFactory;
        $this->_quoteFactory = $quoteFactory;
        $this->_storeManager = $storeManager;
        $this->helper = $helper;
        $this->_messageManager = $messageManager;
    }
 
    /**
     * @inheritdoc
     */
 
    public function testMarkAbandonedCarts($token)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $module_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/active_modul', ScopeInterface::SCOPE_STORE);

        $test_webhook_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/active_test_webhook', ScopeInterface::SCOPE_STORE);

        $test_webhook_token = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/test_webhook_token', ScopeInterface::SCOPE_STORE);

        if (empty($module_enabled) || $module_enabled == 'no') {
            return false;
        }

        if (empty($test_webhook_enabled) || $test_webhook_enabled == 'no') {
            return false;
        }

        if ($token !== $test_webhook_token) {
            return false;
        }

        $response = ['start' => true];

        try {
            $reminderPeriodInHours = $objectManager
                ->get('Magento\Framework\App\Config\ScopeConfigInterface')
                ->getValue('syncplugin/abandoned_cart/hours', ScopeInterface::SCOPE_STORE);

            if (!$reminderPeriodInHours || trim($reminderPeriodInHours) == "") {
                $reminderPeriodInHours = 48;
            }

            $response['period'] = $reminderPeriodInHours;

            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();

            $select = $connection->select()
                ->from(
                    array('q' => $resource->getTableName('quote')),
                    array('store_id' => 'q.store_id',
                        'quote_id' => 'q.entity_id',
                        'customer_id' => 'q.customer_id',
                        'updated_at' => 'q.updated_at'
                    )
                )
                ->joinLeft(
                    array('a' =>$resource->getTableName('quote_address')),
                    'q.entity_id=a.quote_id AND a.address_type="billing"',
                    array('customer_email' => new \Zend_Db_Expr('IFNULL(q.customer_email, a.email)'),
                        'customer_firstname' => new \Zend_Db_Expr('IFNULL(q.customer_firstname, a.firstname)'),
                        'customer_middlename' => new \Zend_Db_Expr('IFNULL(q.customer_middlename, a.middlename)'),
                        'customer_lastname' => new \Zend_Db_Expr('IFNULL(q.customer_lastname, a.lastname)')
                    )
                )
                ->joinInner(
                    array('i' => $resource->getTableName('quote_item')),
                    'q.entity_id=i.quote_id',
                    array(
                        'product_ids' => new \Zend_Db_Expr('GROUP_CONCAT(i.product_id)'),
                        'item_ids' => new \Zend_Db_Expr('GROUP_CONCAT(i.item_id)')
                    )
                )
                ->where('q.is_active=1')
                // go back as far as requested and then two hours
                ->where('q.updated_at > ?', date(
                    'Y-m-d H:i:s',
                    time() - intval($reminderPeriodInHours) * 60 * 60 - 2 * 60 * 60
                ))
                ->where('q.updated_at < ?', date('Y-m-d H:i:s', time() - intval($reminderPeriodInHours) * 60 * 60))
                ->where('q.items_count>0')
                ->where('q.customer_email IS NOT NULL OR a.email IS NOT NULL')
                ->where('i.parent_item_id IS NULL')
                ->group(array('q.entity_id', 'a.email', 'a.firstname', 'a.middlename', 'a.lastname'))
                ->order('updated_at');

            $carts = $connection->fetchAll($select);

            $response['carts'] = $carts;

            foreach ($carts as $cart) {
                try {
                    // FIRST check if there is already a queued transaction that has not already been sent
                    $queueModel = $objectManager->create('Xqueue\Maileon\Model\Queue')->load(
                        $cart['customer_id'],
                        'customer_id'
                    );

                    $response['exists'] = count($queueModel->getData());

                    if (count($queueModel->getData())) {
                        // If there is already an entry for the given customer ID, just continue,
                        // this can be the case if the
                        // abandoned cart checker runs more often than the job for sending transactions
                        continue;
                    } else {
                        $queueModel = $objectManager->get('Xqueue\Maileon\Model\Queue');
                    }

                    // New logic for finding most recent reminder and compare if this one is older than the last check
                    $logModel = $this->_logFactory->create();
                    $abandonedcartsCollection = $logModel->getCollection();
                    $abandonedcartsModel = $abandonedcartsCollection
                        ->addFieldToFilter('customer_id', $cart['customer_id'])
                        ->setOrder('sent_at', 'DESC')
                        ->load()
                        ->getFirstItem();

                    if (!empty($abandonedcartsModel->getData())) {
                        if ($abandonedcartsModel->getSentAt() >= $cart['updated_at']) {
                            continue;
                        }
                    }

                    $this->logger->info('Queuing shopping cart for customer' . $cart['customer_email']);

                    $recipientName = $cart['customer_firstname'] . $cart['customer_lastname'];

                    $queueModel->setUpdatedAt($cart['updated_at']);
                    $queueModel->setCreatedAt(date('Y-m-d H:i:s'));
                    $queueModel->setRecipientName($recipientName);
                    $queueModel->setRecipientEmail($cart['customer_email']);
                    $queueModel->setStoreId($cart['store_id']);
                    $queueModel->setProductIds($cart['product_ids']);
                    $queueModel->setCategoryIds($cart['item_ids']);
                    $queueModel->setCustomerId($cart['customer_id']);
                    $queueModel->setQuoteId($cart['quote_id']);
                    $queueModel->save();
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->_messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with searching abandoned carts: %1', $e->getMessage())
                    );
                }
            }

            $returnArray = json_encode($response);
            return $returnArray;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }
    }

    /**
     * @inheritdoc
     */
 
    public function testSendAbandonedCartsEmails($token)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $apikey = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/api_key', ScopeInterface::SCOPE_STORE);

        $permission = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/permission', ScopeInterface::SCOPE_STORE);

        $print_curl = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/print_curl', ScopeInterface::SCOPE_STORE);

        $module_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/active_modul', ScopeInterface::SCOPE_STORE);

        $shadowEmail = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/shadow_email', ScopeInterface::SCOPE_STORE);

        $overrideEmail = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/email_override', ScopeInterface::SCOPE_STORE);

        $test_webhook_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/active_test_webhook', ScopeInterface::SCOPE_STORE);

        $test_webhook_token = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/abandoned_cart/test_webhook_token', ScopeInterface::SCOPE_STORE);

        if (empty($module_enabled) || $module_enabled == 'no') {
            return false;
        }

        if (empty($apikey) || empty($permission)) {
            return false;
        }

        if (empty($test_webhook_enabled) || $test_webhook_enabled == 'no') {
            return false;
        }

        if ($token !== $test_webhook_token) {
            return false;
        }

        try {
            // Load Abandonedcarts Customer   $objectManager->get('Xqueue\Maileon\Model\Queue');
            $queueModel = $this->_queueFactory->create();
            $abandonedcartsCustomers = $queueModel->getCollection();

            foreach ($abandonedcartsCustomers as $abandonedcartsCustomer) {
                try {
                    // Load template ID
                    $storeId = $abandonedcartsCustomer->getStoreId();

                    $customerId = $abandonedcartsCustomer->getCustomerId();

                    // Abort if there has been a reminder after last change
                    $logModel = $objectManager->create('Xqueue\Maileon\Model\Log')->load(
                        $customerId,
                        'customer_id'
                    );

                    if (count($logModel->getData())) {
                        if ($logModel->getSentAt() >= $abandonedcartsCustomer->getUpdatedAt()) {
                            $abandonedcartsCustomer->delete();
                            continue;
                        }
                    }

                    $quoteModel = $this->_quoteFactory->create();
                    $quoteModelCollection = $quoteModel
                        ->getCollection()
                        ->addFieldToFilter('entity_id', $abandonedcartsCustomer->getQuoteId());

                    $email = $abandonedcartsCustomer->getRecipientEmail();

                    $quote = $quoteModelCollection->getFirstItem();

                    $content = array();
                    $content['cart.id'] = $quote['entity_id'];
                    $content['cart.date'] = $quote['updated_at'];

                    // Get information about the data
                    $items = array();
                    $categories = array();
                    $productIds = array();
                    $cartItems = $quote->getAllVisibleItems();

                    $imagewidth = 200;
                    $imageheight = 200;
                    $imageHelper = $objectManager->get('\Magento\Catalog\Helper\Image');

                    foreach ($cartItems as $cartItem) {
                        $product = $objectManager
                            ->create('\Magento\Catalog\Model\Product')
                            ->load($cartItem->getProductId());

                        $image_url = $imageHelper
                            ->init($product, 'product_page_image_small')
                            ->setImageFile($product->getFile())
                            ->resize($imagewidth, $imageheight)
                            ->getUrl();

                        $item_total = number_format(
                            doubleval($cartItem->getPriceInclTax() * intval($cartItem->getQty())),
                            2,
                            '.',
                            ''
                        );

                        $item_single_price = number_format(
                            doubleval($cartItem->getPriceInclTax()),
                            2,
                            '.',
                            ''
                        );

                        $item = array();
                        $item['id'] = $cartItem->getProductId();
                        $item['sku'] = $cartItem->getSku();
                        $item['title'] = $cartItem->getName();
                        $item['url'] = $product->getProductUrl();
                        $item['image_url'] = htmlspecialchars($image_url, ENT_QUOTES, "UTF-8");
                        $item['thumbnail'] = htmlspecialchars($product->getThumbnail(), ENT_QUOTES, "UTF-8");
                        $item['quantity'] = (int) $cartItem->getQty();
                        $item['single_price'] = $item_single_price;
                        $item['total'] = $item_total;
                        $item['short_description'] = $product->getShortDescription();

                        // Get custom implementations of customer attributes for abandoned cart product
                        $customProductAttributes = $this->helper->getCustomProductAttributes($item);

                        foreach ($customProductAttributes as $key => $value) {
                            $item[$key] = $value;
                        }

                        array_push($items, $item);

                        array_push($productIds, $cartItem->getProductId());

                        $cats = $product->getCategoryIds();
                        foreach ($cats as $category_id) {
                            $_cat = $objectManager->create('\Magento\Catalog\Model\Category')->load($category_id);
                            if (!in_array($_cat->getName(), $categories, true)) {
                                array_push($categories, $_cat->getName());
                            }
                        }
                    }

                    $cart_total = (double)number_format(doubleval($quote['grand_total']), 2, '.', '');
                    $cart_total_tax = (double)number_format(
                        doubleval($quote['grand_total'] - $quote['subtotal']),
                        2,
                        '.',
                        ''
                    );

                    $content['cart.items']       = $items;
                    $content['cart.product_ids'] = join(',', $productIds);
                    $content['cart.categories']  = join(',', $categories);
                    $content['cart.total']       = $cart_total;
                    $content['cart.total_tax']   = $cart_total_tax;
                    $content['cart.currency']    = $quote['base_currency_code'];

                    // Some further customer details
                    $content['customer.salutation'] = $abandonedcartsCustomer->getRecipientPrefix();
                    $content['customer.full_name'] = $abandonedcartsCustomer->getRecipientName();
                    $content['customer.firstname'] = $quote['customer_firstname'];
                    $content['customer.lastname'] = $quote['customer_lastname'];
                    $content['customer.id'] = $customerId;

                    // As the API key can depend on the store ID, use it for sending shopping cart reminders
                    $storeId = $abandonedcartsCustomer->getStoreId();

                    // Get custom implementations of customer attributes for order transaction
                    $customAttributes = $this->helper->getCustomAbandonedCartTransactionAttributes($content);

                    foreach ($customAttributes as $key => $value) {
                        $content[$key] = $value;
                    }

                    // Send event to Maileon
                    $sync = new TransactionCreate($apikey, $print_curl);

                    $standard_fields = array();

                    $custom_fields = array(
                        'magento_storeview_id' => $storeId,
                        'magento_source' => 'abandoned_cart'
                    );
                    
                    $result = $sync->processAbandonedCartReminder(
                        $email,
                        $content,
                        $permission,
                        $shadowEmail,
                        $overrideEmail,
                        $standard_fields,
                        $custom_fields
                    );

                    // add new Log in log table
                    $logModel = $objectManager->get('Xqueue\Maileon\Model\Log');
                    $logModel->setSentAt(date('Y-m-d H:i:s', time()));
                    $logModel->setRecipientName($abandonedcartsCustomer->getRecipientName());
                    $logModel->setRecipientEmail($abandonedcartsCustomer->getRecipientEmail());
                    $logModel->setProductIds($abandonedcartsCustomer->getProductIds());
                    $logModel->setCategoryIds($abandonedcartsCustomer->getCategoryIds());
                    $logModel->setCustomerId($abandonedcartsCustomer->getCustomerId());
                    $logModel->setSentCount(1);
                    $logModel->setQuoteId($abandonedcartsCustomer->getQuoteId());
                    $logModel->setStoreId($abandonedcartsCustomer->getStoreId());
                    $logModel->save();

                    // Remove from queue
                    $abandonedcartsCustomer->delete();
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->_messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with send abandoned carts transaction: %1', $e->getMessage())
                    );
                }
            }

            return true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_messageManager->addExceptionMessage(
                $e,
                __('There was a problem with get abandoned carts: %1', $e->getMessage())
            );
        }
    }
}

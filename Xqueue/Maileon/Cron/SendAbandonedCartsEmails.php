<?php

namespace Xqueue\Maileon\Cron;

use Magento\Store\Model\ScopeInterface;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;

class SendAbandonedCartsEmails
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * @var \Xqueue\Maileon\Model\QueueFactory
     */
    protected $queueFactory;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Xqueue\Maileon\Helper\External\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    public function __construct(
        \Xqueue\Maileon\Logger\Logger $logger,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Xqueue\Maileon\Model\QueueFactory $queueFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Xqueue\Maileon\Helper\External\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->appEmulation = $appEmulation;
        $this->queueFactory = $queueFactory;
        $this->quoteFactory = $quoteFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    public function execute()
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

        if (empty($module_enabled) || $module_enabled == 'no') {
            return false;
        }

        if (empty($apikey) || empty($permission)) {
            return false;
        }

        try {
            // Load Abandonedcarts Customer   $objectManager->get('Xqueue\Maileon\Model\Queue');
            $queueModel = $this->queueFactory->create();
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

                    $quoteModel = $this->quoteFactory->create();
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

                    // Emulate the frontend for the correct image urls
                    $this->appEmulation->startEnvironmentEmulation(
                        $storeId,
                        \Magento\Framework\App\Area::AREA_FRONTEND,
                        true
                    );

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

                        $thumbnail_url = $imageHelper->init($product, 'product_thumbnail_image')->getUrl();

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
                        $item['product_id'] = $cartItem->getProductId();
                        $item['sku'] = $cartItem->getSku();
                        $item['title'] = $cartItem->getName();
                        $item['url'] = $product->getProductUrl();
                        $item['image_url'] = htmlspecialchars($image_url, ENT_QUOTES, "UTF-8");
                        $item['thumbnail'] = htmlspecialchars($thumbnail_url, ENT_QUOTES, "UTF-8");
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

                    // End emulation
                    $this->appEmulation->stopEnvironmentEmulation();

                    $cart_total = (float) number_format(doubleval($quote['grand_total']), 2, '.', '');
                    $cart_total_tax = (float) number_format(
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
                    $this->messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with send abandoned carts transaction: %1', $e->getMessage())
                    );
                }
            }

            return true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with get abandoned carts: %1', $e->getMessage())
            );
        }
    }
}

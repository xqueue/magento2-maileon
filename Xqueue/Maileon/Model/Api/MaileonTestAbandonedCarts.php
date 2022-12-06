<?php
 
namespace Xqueue\Maileon\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;
 
class MaileonTestAbandonedCarts
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * @var \Xqueue\Maileon\Model\LogFactory
     */
    protected $logFactory;

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
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Xqueue\Maileon\Model\LogFactory $logFactory,
        \Xqueue\Maileon\Model\QueueFactory $queueFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Xqueue\Maileon\Helper\External\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->appEmulation = $appEmulation;
        $this->logFactory = $logFactory;
        $this->queueFactory = $queueFactory;
        $this->quoteFactory = $quoteFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }
 
    /**
     * @inheritdoc
     */
 
    public function testMarkAbandonedCarts($token)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $config = $this->getPluginConfigValues();

        $storeIds = array_keys($this->storeManager->getStores(false));

        $enabledStoreIds = $this->checkStoreConfigModuleEnabled($storeIds);

        $validateResult = $this->validateConfigValues($config, $token);

        if (empty($enabledStoreIds)) {
            return json_encode([
                'success' => false,
                'message' => 'None of the stores have the module enabled!'
            ]);
        }

        if (!$validateResult['success']) {
            return json_encode($validateResult);
        }

        $response = ['start' => true];

        try {
            $reminderPeriodInHours = (int) $config['reminderPeriodInHours'];

            $response['period'] = $reminderPeriodInHours;

            $carts = $this->getCartsFromQuote($reminderPeriodInHours, $enabledStoreIds);

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
                        $queueModel = $objectManager->create('Xqueue\Maileon\Model\Queue');
                    }

                    // New logic for finding most recent reminder and compare if this one is older than the last check
                    $logModel = $this->logFactory->create();
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

                    $response['result'] = 'Queuing shopping cart for customer' . $cart['customer_email'];
                    $this->logger->info('Queuing shopping cart for customer' . $cart['customer_email']);

                    $this->saveQueue($queueModel, $cart);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with searching abandoned carts: %1', $e->getMessage())
                    );
                }
            }

            return json_encode($response);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }
    }

    /**
     * Get the config values
     *
     * @param integer|null $storeId
     * @return array
     */
    private function getPluginConfigValues(?int $storeId = null)
    {
        $config['moduleEnabled'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/active_modul',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['testWebhookEnabled'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/active_test_webhook',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $config['testWebhookToken'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/test_webhook_token',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $config['reminderPeriodInHours'] = (int) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/hours',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        return $config;
    }

    /**
     * Checking all storeviews to see if any are the abandoned carts module is enabled.
     *
     * @param array $storeIds
     * @return array
     */
    private function checkStoreConfigModuleEnabled(array $storeIds)
    {
        $enabledStoreIds = [];

        foreach ($storeIds as $storeId) {
            $config = $this->getPluginConfigValues($storeId);
            if ($config['moduleEnabled'] === 'yes') {
                $enabledStoreIds[] = (int) $storeId;
            }
        }

        return $enabledStoreIds;
    }

    /**
     * Validate the config values.
     *
     * @param array $config
     * @param string $token
     * @return array
     */
    private function validateConfigValues(array $config, string $token)
    {
        if (empty($config['testWebhookEnabled']) || $config['testWebhookEnabled'] == 'no') {
            return [
                'success' => false,
                'message' => 'Webhook test disabled! Value: ' . $config['testWebhookEnabled']
            ];
        }

        if (empty($config['reminderPeriodInHours'])) {
            return [
                'success' => false,
                'message' => 'Reminder period in hours is empty!'
            ];
        }

        if (empty($config['testWebhookToken'])) {
            return [
                'success' => false,
                'message' => 'Webhook test token is empty!'
            ];
        }

        if ($token !== $config['testWebhookToken']) {
            return [
                'success' => false,
                'message' => 'Webhook test token not match! Token: ' . $token
            ];
        }

        return [
            'success' => true,
            'message' => 'Every config value is OK!'
        ];
    }

    /**
     * Get the carts from database
     *
     * @param integer $reminderPeriodInHours
     * @param array $storeIds
     * @return array
     */
    protected function getCartsFromQuote(int $reminderPeriodInHours, array $storeIds)
    {
        try {
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from(
                    array('q' => $this->resourceConnection->getTableName('quote')),
                    array('store_id' => 'q.store_id',
                        'quote_id' => 'q.entity_id',
                        'customer_id' => 'q.customer_id',
                        'updated_at' => 'q.updated_at'
                    )
                )
                ->joinLeft(
                    array('a' =>$this->resourceConnection->getTableName('quote_address')),
                    'q.entity_id=a.quote_id AND a.address_type="billing"',
                    array('customer_email' => new \Zend_Db_Expr('IFNULL(q.customer_email, a.email)'),
                        'customer_firstname' => new \Zend_Db_Expr('IFNULL(q.customer_firstname, a.firstname)'),
                        'customer_middlename' => new \Zend_Db_Expr('IFNULL(q.customer_middlename, a.middlename)'),
                        'customer_lastname' => new \Zend_Db_Expr('IFNULL(q.customer_lastname, a.lastname)')
                    )
                )
                ->joinInner(
                    array('i' => $this->resourceConnection->getTableName('quote_item')),
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
                ->where('q.store_id IN (?)', $storeIds)
                ->where('q.items_count>0')
                ->where('q.customer_email IS NOT NULL OR a.email IS NOT NULL')
                ->where('i.parent_item_id IS NULL')
                ->group(array('q.entity_id', 'a.email', 'a.firstname', 'a.middlename', 'a.lastname'))
                ->order('updated_at');

            return $connection->fetchAll($select);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }
    }

    /**
     * Save the Queue to database
     *
     * @param \Xqueue\Maileon\Model\Queue $queueModel
     * @param array $cart
     * @return void
     */
    protected function saveQueue(\Xqueue\Maileon\Model\Queue $queueModel, array $cart)
    {
        $recipientName = $cart['customer_firstname'] . ' ' . $cart['customer_lastname'];

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
    }

    /**
     * @inheritdoc
     */
 
    public function testSendAbandonedCartsEmails($token)
    {
        $isEnabledWebhookTest = $this->isEnabledWebhookTest($token);

        if (!$isEnabledWebhookTest['success']) {
            return json_encode($isEnabledWebhookTest);
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        try {
            // Load Abandonedcarts Customer
            $queueModel = $this->queueFactory->create();
            $abandonedCartCustomers = $queueModel->getCollection();

            $successfulSending = 0;
            $foundRecord = 0;
            $configProblem = 0;
            $alreadySent = 0;
            $failed = 0;

            foreach ($abandonedCartCustomers as $abandonedCartCustomer) {
                try {
                    // Load store id
                    $storeId = (int) $abandonedCartCustomer->getStoreId();
                    $foundRecord++;

                    $config = $this->getSendAbandonedCartsConfig($storeId);
                    $checkConfig = $this->checkSendAbandonedCartsConfigValues($config);

                    if (!$checkConfig['success']) {
                        $this->logger->error('Problem with the config values: ' . $checkConfig['message']);
                        $configProblem++;
                        continue;
                    }

                    // Abort if there has been a reminder after last change
                    $logModel = $objectManager->create('Xqueue\Maileon\Model\Log')->load(
                        $abandonedCartCustomer->getCustomerId(),
                        'customer_id'
                    );

                    if (count($logModel->getData())) {
                        if ($logModel->getSentAt() >= $abandonedCartCustomer->getUpdatedAt()) {
                            $abandonedCartCustomer->delete();
                            $alreadySent++;
                            continue;
                        }
                    }

                    $quoteModel = $this->quoteFactory->create();
                    $quoteModelCollection = $quoteModel
                        ->getCollection()
                        ->addFieldToFilter('entity_id', $abandonedCartCustomer->getQuoteId());

                    $quote = $quoteModelCollection->getFirstItem();

                    $content = $this->createAbandonedCartTransactionContent(
                        $quote,
                        $storeId,
                        $abandonedCartCustomer
                    );

                    // Send transaction to Maileon
                    $sendToMaileonResult = $this->sendAbandonedCartTransaction(
                        $abandonedCartCustomer,
                        $content,
                        $storeId,
                        $config
                    );

                    if ($sendToMaileonResult) {
                        // Add new Log in log table
                        $logModel = $objectManager->create('Xqueue\Maileon\Model\Log');
                        $this->saveAbandonedCartToLog($logModel, $abandonedCartCustomer);

                        // Remove from Queue
                        $abandonedCartCustomer->delete();

                        $successfulSending++;
                    } else {
                        $failed++;
                    }
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->messageManager->addExceptionMessage(
                        $e,
                        __('There was a problem with send abandoned carts transaction: %1', $e->getMessage())
                    );
                }
            }

            $response['result'] = 'Founded record: ' . $foundRecord .
            ' Problem with config: ' . $configProblem .
            ' Already sent: ' . $alreadySent .
            ' Successful sending: ' . $successfulSending .
            ' Failed: ' . $failed;

            return json_encode($response);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with get abandoned carts: %1', $e->getMessage())
            );
        }
    }

    /**
     * Get plugin config values
     *
     * @param integer|null $storeId
     * @return array
     */
    private function getSendAbandonedCartsConfig(?int $storeId = null)
    {
        $config['moduleEnabled'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/active_modul',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['apiKey'] = (string) $this->scopeConfig->getValue(
            'syncplugin/general/api_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['permission'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/permission',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['printCurl'] = (string) $this->scopeConfig->getValue(
            'syncplugin/general/print_curl',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['shadowEmail'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/shadow_email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $config['overrideEmail'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/email_override',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $config;
    }

    /**
     * Validate config values
     *
     * @param array $config
     * @return array
     */
    private function checkSendAbandonedCartsConfigValues(array $config)
    {
        if (empty($config['moduleEnabled']) || $config['moduleEnabled'] === 'no') {
            return [
                'success' => false,
                'message' => 'Module disabled! Value: ' . $config['moduleEnabled']
            ];
        }

        if (empty($config['apiKey'])) {
            return [
                'success' => false,
                'message' => 'Maileon API key is empty!'
            ];
        }

        if (empty($config['permission'])) {
            return [
                'success' => false,
                'message' => 'Permission is empty!'
            ];
        }

        return [
            'success' => true,
            'message' => 'Every config value is OK!'
        ];
    }

    /**
     * Check webhook testing is enabled or not
     *
     * @param string $token
     * @return array
     */
    private function isEnabledWebhookTest(string $token)
    {
        $testWebhookEnabled = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/active_test_webhook',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        $testWebhookToken = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/test_webhook_token',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        if (empty($testWebhookEnabled) || $testWebhookEnabled === 'no') {
            return [
                'success' => false,
                'message' => 'Webhook test disabled! Value: ' . $testWebhookEnabled
            ];
        }

        if (empty($testWebhookToken)) {
            return [
                'success' => false,
                'message' => 'Webhook test token is empty!'
            ];
        }

        if ($token !== $testWebhookToken) {
            return [
                'success' => false,
                'message' => 'Webhook test token not match! Token: ' . $token
            ];
        }

        return [
            'success' => true,
            'message' => 'Test webhhok enabled!'
        ];
    }

    /**
     * Create abandoned cart items array
     *
     * @param \Magento\Framework\DataObject $quote
     * @param integer $storeId
     * @return array
     */
    protected function createCartItems(\Magento\Framework\DataObject $quote, int $storeId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        // Get information about the data
        $items = array();
        $categories = array();
        $productIds = array();
        $cartItems = $quote->getAllVisibleItems();

        $imagewidth = 200;
        $imageheight = 200;

        // Emulate the frontend for the correct image urls, that need only in API
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

            $imageUrl = $imageHelper
                ->init($product, 'product_page_image_small')
                ->setImageFile($product->getFile())
                ->resize($imagewidth, $imageheight)
                ->getUrl();

            $thumbnailUrl = $imageHelper->init($product, 'product_thumbnail_image')->getUrl();

            $itemTotal = number_format(
                doubleval($cartItem->getPriceInclTax() * intval($cartItem->getQty())),
                2,
                '.',
                ''
            );

            $itemSinglePrice = number_format(
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
            $item['image_url'] = htmlspecialchars($imageUrl, ENT_QUOTES, "UTF-8");
            $item['thumbnail'] = htmlspecialchars($thumbnailUrl, ENT_QUOTES, "UTF-8");
            $item['quantity'] = (int) $cartItem->getQty();
            $item['single_price'] = $itemSinglePrice;
            $item['total'] = $itemTotal;
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

        return [
            'items' => $items,
            'categories' => $categories,
            'productIds' => $productIds
        ];
    }

    /**
     * Create abandoned cart transaction content
     *
     * @param \Magento\Framework\DataObject $quote
     * @param integer $storeId
     * @param object $customer
     * @return array
     */
    protected function createAbandonedCartTransactionContent(
        \Magento\Framework\DataObject $quote,
        int $storeId,
        object $customer,
    ) {
        $content = array();

        $content['cart.id'] = $quote['entity_id'];
        $content['cart.date'] = $quote['updated_at'];

        $cartItems = $this->createCartItems($quote, $storeId);

        $cartTotal = (double) number_format(doubleval($quote['grand_total']), 2, '.', '');
        $cartTotalTax = (double) number_format(
            doubleval($quote['grand_total'] - $quote['subtotal']),
            2,
            '.',
            ''
        );

        $content['cart.items']       = $cartItems['items'];
        $content['cart.product_ids'] = join(',', $cartItems['productIds']);
        $content['cart.categories']  = join(',', $cartItems['categories']);
        $content['cart.total']       = $cartTotal;
        $content['cart.total_tax']   = $cartTotalTax;
        $content['cart.currency']    = $quote['base_currency_code'];

        // Some further customer details
        $content['customer.salutation'] = $customer->getRecipientPrefix();
        $content['customer.full_name'] = $customer->getRecipientName();
        $content['customer.firstname'] = $quote['customer_firstname'];
        $content['customer.lastname'] = $quote['customer_lastname'];
        $content['customer.id'] = $customer->getCustomerId();

        // Get custom implementations of customer attributes for order transaction
        $customAttributes = $this->helper->getCustomAbandonedCartTransactionAttributes($content);

        foreach ($customAttributes as $key => $value) {
            $content[$key] = $value;
        }

        return $content;
    }

    /**
     * Send abandoned cart transaction to Maileon
     *
     * @param object $customer
     * @param array $content
     * @param integer $storeId
     * @param array $config
     * @return boolean
     */
    protected function sendAbandonedCartTransaction(
        object $customer,
        array $content,
        int $storeId,
        array $config
    ) {
        $transactionCreate = new TransactionCreate($config['apiKey'], $config['printCurl']);

        $standardFields = array(
            'FULLNAME' => $customer->getRecipientName()
        );

        $customFields = array(
            'magento_storeview_id' => $storeId,
            'magento_source' => 'abandoned_cart'
        );
        
        $result = $transactionCreate->processAbandonedCartReminder(
            $customer->getRecipientEmail(),
            $content,
            $config['permission'],
            $config['shadowEmail'],
            $config['overrideEmail'],
            $standardFields,
            $customFields
        );

        return $result;
    }

    /**
     * Save abandoned cart to Log table
     *
     * @param object $customer
     * @return void
     */
    protected function saveAbandonedCartToLog(\Xqueue\Maileon\Model\Log $logModel, object $customer)
    {
        $logModel->setSentAt(date('Y-m-d H:i:s', time()));
        $logModel->setRecipientName($customer->getRecipientName());
        $logModel->setRecipientEmail($customer->getRecipientEmail());
        $logModel->setProductIds($customer->getProductIds());
        $logModel->setCategoryIds($customer->getCategoryIds());
        $logModel->setCustomerId($customer->getCustomerId());
        $logModel->setSentCount(1);
        $logModel->setQuoteId($customer->getQuoteId());
        $logModel->setStoreId($customer->getStoreId());
        $logModel->setUpdatedAt(date('Y-m-d H:i:s'));
        $logModel->setCreatedAt(date('Y-m-d H:i:s'));
        $logModel->save();
    }
}

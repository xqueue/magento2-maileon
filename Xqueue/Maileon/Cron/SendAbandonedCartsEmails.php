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
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

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
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Xqueue\Maileon\Model\QueueFactory $queueFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Xqueue\Maileon\Helper\External\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
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

        try {
            // Load Abandonedcarts Customer
            $queueModel = $this->queueFactory->create();
            $abandonedCartCustomers = $queueModel->getCollection();

            foreach ($abandonedCartCustomers as $abandonedCartCustomer) {
                try {
                    // Load store id
                    $storeId = (int) $abandonedCartCustomer->getStoreId();

                    $config = $this->getSendAbandonedCartsConfig($storeId);
                    $checkConfig = $this->checkSendAbandonedCartsConfigValues($config);

                    if (!$checkConfig['success']) {
                        $this->logger->error('Problem with the config values: ' . $checkConfig['message']);
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
                    }
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

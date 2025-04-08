<?php

namespace Xqueue\Maileon\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Xqueue\Maileon\Model\Maileon\ContactCreate;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;
use de\xqueue\maileon\api\client\contacts\Contact;

class TestSendAbandonedCartsEmails
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

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
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Xqueue\Maileon\Helper\External\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->scopeConfig = $scopeConfig;
        $this->appEmulation = $appEmulation;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     */

    public function testSendAbandonedCartsEmails($token)
    {
        $successfulSending = 0;
        $foundRecord = 0;
        $configProblem = 0;
        $alreadySent = 0;
        $failed = 0;

        try {
            $maileonQueueCollection = $this->objectManager->create(
                'Xqueue\Maileon\Model\ResourceModel\MaileonQueue\Collection'
            );

            foreach ($maileonQueueCollection as $maileonQueueModel) {
                $foundRecord++;

                $config = $this->getSendAbandonedCartsConfig($maileonQueueModel->getStoreId());
                $checkConfig = $this->checkSendAbandonedCartsConfigValues($config);

                if (!$checkConfig['success']) {
                    $this->logger->error('Problem with the config values: ' . $checkConfig['message']);
                    $configProblem++;
                    continue;
                }

                if ($this->isAlreadySentWithSameContent($maileonQueueModel)) {
                    $maileonQueueModel->delete();
                    $alreadySent++;
                    continue;
                }

                $content = $this->createAbandonedCartTransactionContent($maileonQueueModel);

                // Send transaction to Maileon
                $sendToMaileonResult = $this->processAbandonedCartTransactions(
                    $maileonQueueModel,
                    $content,
                    $config
                );

                if ($sendToMaileonResult) {
                    $this->saveAbandonedCartToLog($maileonQueueModel, $content['cart.product_ids']);
                    $successfulSending++;

                    // Remove from Queue
                    $maileonQueueModel->delete();
                }

                $response['result'] = 'Founded record: ' . $foundRecord .
                    ' Problem with config: ' . $configProblem .
                    ' Already sent: ' . $alreadySent .
                    ' Successful sending: ' . $successfulSending .
                    ' Failed: ' . $failed;

                return json_encode($response);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $failed++;
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with get abandoned carts: %1', $e->getMessage())
            );
        }

        return 'failed';
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
     * Check if there is already sent transaction with the same metrics
     *
     * @param \Xqueue\Maileon\Model\MaileonQueue $maileonQueue
     * @return boolean
     */
    protected function isAlreadySentWithSameContent(\Xqueue\Maileon\Model\MaileonQueue $maileonQueue): bool
    {
        $maileonLogCollection = $this->objectManager->create(
            'Xqueue\Maileon\Model\ResourceModel\MaileonLog\Collection'
        );
        $maileonLogCollection->addFieldToFilter('quote_id', $maileonQueue->getQuoteId())
            ->addFieldToFilter('quote_hash', $maileonQueue->getQuoteHash());

        if (count($maileonLogCollection->getData())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create abandoned cart items array
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    protected function createCartItems(\Magento\Quote\Model\Quote $quote)
    {
        // Get information about the data
        $items = array();
        $productIds = array();
        $quoteItems = $quote->getAllVisibleItems();
        $categories = '';

        // Emulate the frontend for the correct image urls, that need only in API
        $this->appEmulation->startEnvironmentEmulation(
            $quote->getStoreId(),
            \Magento\Framework\App\Area::AREA_FRONTEND,
            true
        );

        foreach ($quoteItems as $quoteItem) {
            $product = $this->objectManager
                ->create('\Magento\Catalog\Model\Product')
                ->load($quoteItem->getProductId());

            $item = array();
            $item['id'] = $quoteItem->getProductId();
            $item['sku'] = $quoteItem->getSku();
            $item['title'] = $quoteItem->getName();
            $item['url'] = $product->getProductUrl();
            $item['image_url'] = htmlspecialchars($this->getProductImageUrl($product), ENT_QUOTES, "UTF-8");
            $item['thumbnail'] = htmlspecialchars($this->getProductThumbnailUrl($product), ENT_QUOTES, "UTF-8");
            $item['quantity'] = (int) $quoteItem->getQty();
            $item['single_price'] = $this->formatPrice($quoteItem->getPriceInclTax());
            $item['total'] = $this->formatPrice($quoteItem->getPriceInclTax() * intval($quoteItem->getQty()));
            $item['short_description'] = $product->getShortDescription();

            // Get custom implementations of customer attributes for abandoned cart product
            $customProductAttributes = $this->helper->getCustomProductAttributes($item);

            foreach ($customProductAttributes as $key => $value) {
                $item[$key] = $value;
            }

            array_push($items, $item);

            array_push($productIds, $quoteItem->getProductId());

            if (empty($categories)) {
                $categories .= $this->getProductCategories($product);
            } else {
                $categories .= ',' . $this->getProductCategories($product);
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
     * @param \Xqueue\Maileon\Model\MaileonQueue $maileonQueue
     * @return array
     */
    protected function createAbandonedCartTransactionContent(\Xqueue\Maileon\Model\MaileonQueue $maileonQueue)
    {
        $quoteModelCollection = $this->objectManager->create('Magento\Reports\Model\ResourceModel\Quote\Collection');
        $quoteModelCollection->addFieldToFilter('entity_id', $maileonQueue->getQuoteId());
        /** @var Quote $quote  */
        $quote = $quoteModelCollection->getFirstItem();

        $content = array();

        $content['cart.id'] = $quote->getEntityId();
        $content['cart.date'] = $quote->getUpdatedAt();

        $cartItems = $this->createCartItems($quote);

        $content['cart.items']       = $cartItems['items'];
        $content['cart.product_ids'] = $this->sanitizeProductIdList($cartItems['productIds']);
        $content['cart.categories']  = $this->sanitizeCategoriesList($cartItems['categories']);
        $content['cart.total']       = (float) $this->formatPrice($quote->getGrandTotal());
        $content['cart.total_tax']   = (float) $this->formatPrice($quote->getGrandTotal() - $quote->getSubtotal());
        $content['cart.currency']    = $quote->getBaseCurrencyCode();

        // Some further customer details
        $fullname = $quote->getCustomerFirstname() . $quote->getCustomerLastname();
        $content['customer.salutation'] = $quote->getCustomerPrefix();
        $content['customer.full_name'] = $fullname;
        $content['customer.firstname'] = $quote->getCustomerFirstname();
        $content['customer.lastname'] = $quote->getCustomerLastname();
        $content['customer.id'] = $quote->getCustomerId();
        $content['generic.string_1'] = $quote->getStoreId() !== null ? (string) $quote->getStoreId() : '';
        $content['generic.string_2'] = $quote->getStore() ? $quote->getStore()->getName() : '';

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
     * @param \Xqueue\Maileon\Model\MaileonQueue $maileonQueue
     * @param array $content
     * @param array $config
     * @return boolean
     */
    protected function sendAbandonedCartTransaction(
        Contact $contact,
        array $content,
        array $config
    ) {
        $contactCreated = $this->updateOrCreateContact($contact, $config);

        if ($contactCreated) {
            $transactionCreate = new TransactionCreate($config['apiKey'], $config['printCurl']);

            return $transactionCreate->sendTransaction(
                $contact->email,
                'magento_abandoned_carts_v2',
                $content
            );
        }

        return false;
    }

    /**
     * Process abandoned cart transactions
     *
     * @param \Xqueue\Maileon\Model\MaileonQueue $maileonQueue
     * @param array $content
     * @param array $config
     * @return boolean
     */
    protected function processAbandonedCartTransactions(
        \Xqueue\Maileon\Model\MaileonQueue $maileonQueue,
        array $content,
        array $config
    ): bool {
        $contact = new Contact();
        $contact->email = $maileonQueue->getRecipientEmail();
        $contact->standard_fields = array(
            'FULLNAME' => $maileonQueue->getRecipientName()
        );

        $contact->custom_fields = array(
            'magento_storeview_id' => $maileonQueue->getStoreId(),
            'magento_source' => 'abandoned_cart'
        );

        // Check wether to set the original email or the override email address
        if (!empty($config['overrideEmail'])) {
            $contact->email = $config['overrideEmail'];
        }

        $result = $this->sendAbandonedCartTransaction(
            $contact,
            $content,
            $config
        );

        // Check wether to set the original email or the override email address
        if (!empty($config['shadowEmail'])) {
            $contact->email = $config['shadowEmail'];

            $this->sendAbandonedCartTransaction(
                $contact,
                $content,
                $config
            );
        }

        return $result;
    }

    /**
     * Update or create the contact at Maileon
     *
     * @param Contact $contact
     * @param array $config
     * @return boolean
     */
    protected function updateOrCreateContact(Contact $contact, array $config): bool
    {
        $contactCreate = new ContactCreate(
            $config['apiKey'],
            $contact->email,
            'none',
            false,
            false,
            null,
            $config['printCurl']
        );

        return $contactCreate->makeMalieonContact(
            array(),
            $contact->standard_fields,
            $contact->custom_fields
        );
    }

    /**
     * Save abandoned cart to Log table
     *
     * @param \Xqueue\Maileon\Model\MaileonQueue $maileonQueue
     * @param string $productIds
     * @return void
     */
    protected function saveAbandonedCartToLog(
        \Xqueue\Maileon\Model\MaileonQueue $maileonQueue,
        string $productIds
    ): void {
        $maileonLogModel = $this->objectManager->create('Xqueue\Maileon\Model\MaileonLog');

        $maileonLogModel->setSentAt(date('Y-m-d H:i:s', time()));
        $maileonLogModel->setRecipientName($maileonQueue->getRecipientName());
        $maileonLogModel->setRecipientEmail($maileonQueue->getRecipientEmail());
        $maileonLogModel->setProductIds($productIds);
        $maileonLogModel->setCustomerId($maileonQueue->getCustomerId());
        $maileonLogModel->setSentCount(1);
        $maileonLogModel->setQuoteId($maileonQueue->getQuoteId());
        $maileonLogModel->setStoreId($maileonQueue->getStoreId());
        $maileonLogModel->setQuoteHash($maileonQueue->getQuoteHash());
        $maileonLogModel->setUpdatedAt(date('Y-m-d H:i:s'));
        $maileonLogModel->setCreatedAt(date('Y-m-d H:i:s'));
        $maileonLogModel->save();
    }

    /**
     * Get the product categories list
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getProductCategories(\Magento\Catalog\Model\Product $product): string
    {
        $categoryIds = $product->getCategoryIds();
        $productCategories = '';

        if (!empty($categoryIds)) {
            $categories = [];

            foreach ($categoryIds as $categoryId) {
                $category = $this->objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
                $categories[] = $category->getName();
            }

            $productCategories = implode(',', $categories);
        }

        return $productCategories;
    }

    /**
     * Get the product image url
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getProductImageUrl(\Magento\Catalog\Model\Product $product): string
    {
        $imagewidth = 200;
        $imageheight = 200;
        $imageHelper = $this->objectManager->get('\Magento\Catalog\Helper\Image');

        try {
            $imageUrl = $imageHelper
                ->init($product, 'product_page_image_small')
                ->setImageFile($product->getFile())
                ->resize($imagewidth, $imageheight)
                ->getUrl();
        } catch (\Exception $e) {
            $imageUrl = '';
        }

        return $imageUrl;
    }

    /**
     * Get the product thumbnail url
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getProductThumbnailUrl(\Magento\Catalog\Model\Product $product): string
    {
        $imageHelper = $this->objectManager->get('\Magento\Catalog\Helper\Image');

        try {
            $imageUrl = $imageHelper
                ->init($product, 'product_thumbnail_image')
                ->getUrl();
        } catch (\Exception $e) {
            $imageUrl = '';
        }

        return $imageUrl;
    }

    /**
     * Format price value
     *
     * @param mixed $price
     * @return string
     */
    protected function formatPrice($price): string
    {
        return number_format(
            doubleval($price),
            2,
            '.',
            ''
        );
    }

    /**
     * @param string $categoriesList
     * @return string
     */
    protected function sanitizeCategoriesList(string $categoriesList): string
    {
        if (empty($categoriesList)) {
            return '';
        }

        $categories = explode(',', $categoriesList);
        $categories = array_filter(array_map('trim', $categories));
        $uniqueCategories = array_unique($categories);
        $categoriesList = implode(',', $uniqueCategories);

        return $this->sanitizeTransactionStringValue($categoriesList);
    }

    /**
     * @param array $productIds
     * @return string
     */
    protected function sanitizeProductIdList(array $productIds): string
    {
        if (empty($productIds)) {
            return '';
        }

        $productIds = array_filter(array_map('trim', $productIds));
        $uniqueProductIds = array_unique($productIds);
        $productIdList = implode(',', $uniqueProductIds);

        return $this->sanitizeTransactionStringValue($productIdList);
    }

    /**
     * @param $value
     * @return string
     */
    protected function sanitizeTransactionStringValue($value): string
    {
        if (!empty($value)) {
            return mb_substr($value, 0, 1000);
        } else {
            return '';
        }
    }
}

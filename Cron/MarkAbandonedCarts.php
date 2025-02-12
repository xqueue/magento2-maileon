<?php

namespace Xqueue\Maileon\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Reports\Model\ResourceModel\Quote\Collection;
use Magento\Store\Model\ScopeInterface;

class MarkAbandonedCarts
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
    }

    public function execute()
    {
        $config = $this->getPluginConfigValues();
        $storeIds = array_keys($this->storeManager->getStores(false));
        $enabledStoreIds = $this->checkStoreConfigModuleEnabled($storeIds);
        $validateResult = $this->validateConfigValues($config);

        if (empty($enabledStoreIds)) {
            return false;
        }

        if (!$validateResult['success']) {
            return false;
        }

        try {
            $quotes = $this->getFilteredQuotes($config['reminderPeriodInHours'], $enabledStoreIds);

            foreach ($quotes as $quote) {
                if ($this->isAlreadyInQueue($quote)) {
                    continue;
                }

                if ($this->isAlreadySentWithSameContent($quote)) {
                    continue;
                }

                $this->logger->info('Queuing shopping cart for customer' . $quote->getCustomerEmail());

                $this->saveToMaileonQueue($quote);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }

        return true;
    }

    /**
     * Get the config values
     *
     * @param integer|null $storeId
     * @return array
     */
    private function getPluginConfigValues(?int $storeId = null): array
    {
        $config['moduleEnabled'] = (string) $this->scopeConfig->getValue(
            'syncplugin/abandoned_cart/active_modul',
            ScopeInterface::SCOPE_STORE,
            $storeId
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
    private function checkStoreConfigModuleEnabled(array $storeIds): array
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
     * @return array
     */
    private function validateConfigValues(array $config): array
    {
        if (empty($config['reminderPeriodInHours'])) {
            return [
                'success' => false,
                'message' => 'Reminder period in hours is empty!'
            ];
        }

        return [
            'success' => true,
            'message' => 'Every config value is OK!'
        ];
    }

    /**
     * Get the filtered qoutes from database
     *
     * @param integer $reminderPeriodInHours
     * @param array $storeIds
     * @return Collection
     */
    protected function getFilteredQuotes(int $reminderPeriodInHours, array $storeIds): Collection
    {
        $dates = $this->getFromAndToDates($reminderPeriodInHours);

        try {
            $collection = $this->objectManager->create('Magento\Reports\Model\ResourceModel\Quote\Collection');
            $collection->addFieldToFilter('items_count', array('neq' => '0'))
                ->addFieldToFilter('main_table.is_active', '1')
                ->addFieldToFilter('main_table.store_id', array('in' => $storeIds))
                ->addFieldToFilter('main_table.customer_email', array('neq' => ''))
                ->addFieldToFilter('main_table.updated_at', array('from' => $dates['from'], 'to' => $dates['to']))
                ->setOrder('updated_at');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('There was a problem with searching abandoned carts: %1', $e->getMessage())
            );
        }

        return $collection;
    }


    /**
     * Check if there is already a queued transaction that has not already been sent
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return boolean
     */
    protected function isAlreadyInQueue(\Magento\Quote\Model\Quote $quote): bool
    {
        $maileonQueueCollection = $this->objectManager->create(
            'Xqueue\Maileon\Model\ResourceModel\MaileonQueue\Collection'
        );
        $maileonQueueCollection->addFieldToFilter('quote_id', $quote->getId());

        if (count($maileonQueueCollection->getData())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if there is already sent transaction with the same metrics
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return boolean
     */
    protected function isAlreadySentWithSameContent(\Magento\Quote\Model\Quote $quote): bool
    {
        $maileonLogCollection = $this->objectManager->create(
            'Xqueue\Maileon\Model\ResourceModel\MaileonLog\Collection'
        );
        $maileonLogCollection->addFieldToFilter('quote_id', $quote->getId())
            ->addFieldToFilter('quote_hash', $this->getHash($quote));

        if (count($maileonLogCollection->getData())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create hash from Quote items count and grand total
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return string
     */
    private function getHash(\Magento\Quote\Model\Quote $quote): string
    {
        $valuesToHash = (string) $quote->getItemsCount() . (string) $quote->getGrandTotal();
        return hash('md5', $valuesToHash);
    }

    /**
     * Create the from and to dates from reminderPeriodInHours
     *
     * @param integer $reminderPeriodInHours
     * @return array
     */
    private function getFromAndToDates(int $reminderPeriodInHours): array
    {
        $fromTimestamp = time() - intval($reminderPeriodInHours) * 60 * 60;
        $toTimestamp = $fromTimestamp + (5 * 60);

        return [
            'from' => date('Y-m-d H:i:s', $fromTimestamp),
            'to' => date('Y-m-d H:i:s', $toTimestamp)
        ];
    }

    /**
     * Save the MaileonQueue to database
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return void
     */
    protected function saveToMaileonQueue(\Magento\Quote\Model\Quote $quote): void
    {
        $maileonQueueModel = $this->objectManager->create('Xqueue\Maileon\Model\MaileonQueue');

        $recipientName = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();

        $maileonQueueModel->setRecipientName($recipientName);
        $maileonQueueModel->setRecipientEmail($quote->getCustomerEmail());
        $maileonQueueModel->setStoreId($quote->getStoreId());
        $maileonQueueModel->setCustomerId($quote->getCustomerId());
        $maileonQueueModel->setQuoteId($quote->getId());
        $maileonQueueModel->setQuoteTotal($quote->getGrandTotal());
        $maileonQueueModel->setQuoteHash($this->getHash($quote));
        $maileonQueueModel->setItemsCount($quote->getItemsCount());
        $maileonQueueModel->setUpdatedAt(date('Y-m-d H:i:s'));
        $maileonQueueModel->setCreatedAt(date('Y-m-d H:i:s'));
        $maileonQueueModel->save();
    }
}

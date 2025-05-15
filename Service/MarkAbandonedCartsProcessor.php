<?php

namespace Xqueue\Maileon\Service;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Quote\Model\Quote;
use Throwable;
use Xqueue\Maileon\Model\ResourceModel\MaileonQueue\CollectionFactory as MaileonQueueCollectionFactory;
use Xqueue\Maileon\Model\ResourceModel\MaileonLog\CollectionFactory as MaileonLogCollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Xqueue\Maileon\Model\MaileonQueueFactory;
use Xqueue\Maileon\Model\ResourceModel\MaileonQueue as MaileonQueueResource;
use Magento\Reports\Model\ResourceModel\Quote\Collection;
use Magento\Reports\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Logger\Logger;

class MarkAbandonedCartsProcessor
{
    public function __construct(
        private Logger $logger,
        private Config $config,
        private QuoteCollectionFactory $quoteCollectionFactory,
        private MaileonQueueCollectionFactory $maileonQueueCollectionFactory,
        private MaileonLogCollectionFactory $maileonLogCollectionFactory,
        private MaileonQueueFactory $maileonQueueFactory,
        private MaileonQueueResource $maileonQueueResource,
        private StoreManagerInterface $storeManager,
        private DateTime $dateTime
    ) {}

    public function execute(): array
    {
        $config = $this->getPluginConfigValues();
        $storeIds = array_keys($this->storeManager->getStores(false));
        $enabledStoreIds = $this->checkStoreConfigModuleEnabled($storeIds);
        $validateResult = $this->validateConfigValues($config);
        $result = [
            'enabledStoreIds' => $enabledStoreIds,
            'validateResult' => $validateResult,
            'foundQuotes' => 0,
            'savedQuotes' => 0,
        ];

        if (empty($enabledStoreIds)) {
            return $result;
        }

        if (!$validateResult['success']) {
            return $result;
        }

        try {
            $quotes = $this->getFilteredQuotes($config['reminderPeriodInHours'], $enabledStoreIds);

            foreach ($quotes as $quote) {
                $result['foundQuotes']++;
                if ($this->isAlreadyInQueue($quote)) {
                    continue;
                }

                if ($this->isAlreadySentWithSameContent($quote)) {
                    continue;
                }

                $this->logger->info('Queuing shopping cart for customer' . $quote->getCustomerEmail());

                $this->saveToMaileonQueue($quote);
                $result['savedQuotes']++;
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        return $result;
    }

    /**
     * Get the config values
     *
     * @param integer|null $storeId
     * @return array
     */
    private function getPluginConfigValues(?int $storeId = null): array
    {
        $config['moduleEnabled'] = $this->config->isAbandonedCartEnabled($storeId);
        $config['reminderPeriodInHours'] = $this->config->getAbandonedCartHours();

        return $config;
    }

    /**
     * Checking all store views to see if any are the abandoned carts module is enabled.
     *
     * @param array $storeIds
     * @return array
     */
    private function checkStoreConfigModuleEnabled(array $storeIds): array
    {
        $enabledStoreIds = [];

        foreach ($storeIds as $storeId) {
            $config = $this->getPluginConfigValues($storeId);
            if ($config['moduleEnabled']) {
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
     */
    protected function getFilteredQuotes(int $reminderPeriodInHours, array $storeIds): Collection
    {
        $dates = $this->getFromAndToDates($reminderPeriodInHours);

        try {
            /** @var Collection $collection */
            $collection = $this->quoteCollectionFactory->create();

            $collection->addFieldToFilter('items_count', ['neq' => '0']);
            $collection->addFieldToFilter('main_table.is_active', '1');
            $collection->addFieldToFilter('main_table.store_id', ['in' => $storeIds]);
            $collection->addFieldToFilter('main_table.customer_email', ['neq' => '']);
            $collection->addFieldToFilter('main_table.updated_at', [
                'from' => $dates['from'],
                'to'   => $dates['to'],
            ]);
            $collection->setOrder('updated_at');

            return $collection;

        } catch (Exception $exception) {
            $this->logger->error('Abandoned cart filtering failed: ' . $exception->getMessage());

            return $this->quoteCollectionFactory->create()->addFieldToFilter('entity_id', -1);
        }
    }


    /**
     * Check if there is already a queued transaction that has not already been sent
     *
     * @param Quote $quote
     * @return boolean
     */
    protected function isAlreadyInQueue(Quote $quote): bool
    {
        $collection = $this->maileonQueueCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', $quote->getId());
        $collection->setPageSize(1);

        return (bool) $collection->getSize();
    }

    /**
     * Check if there is already sent transaction with the same metrics
     *
     * @param Quote $quote
     * @return boolean
     */
    protected function isAlreadySentWithSameContent(Quote $quote): bool
    {
        $collection = $this->maileonLogCollectionFactory->create();

        $collection->addFieldToFilter('quote_id', $quote->getId());
        $collection->addFieldToFilter('quote_hash', $this->getHash($quote));
        $collection->setPageSize(1);

        return (bool) $collection->getSize();
    }

    /**
     * Create hash from Quote items count and grand total
     *
     * @param Quote $quote
     * @return string
     */
    private function getHash(Quote $quote): string
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
    public function getFromAndToDates(int $reminderPeriodInHours): array
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
     * @param Quote $quote
     * @return void
     * @throws AlreadyExistsException
     */
    protected function saveToMaileonQueue(Quote $quote): void
    {
        $maileonQueue = $this->maileonQueueFactory->create();

        $recipientName = trim($quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname());

        $maileonQueue->setData([
            'recipient_name' => $recipientName,
            'recipient_email' => $quote->getCustomerEmail(),
            'store_id' => $quote->getStoreId(),
            'customer_id' => $quote->getCustomerId(),
            'quote_id' => $quote->getId(),
            'quote_total' => $quote->getGrandTotal(),
            'quote_hash' => $this->getHash($quote),
            'items_count' => $quote->getItemsCount(),
            'updated_at' => $this->dateTime->gmtDate(),
            'created_at' => $this->dateTime->gmtDate(),
        ]);

        $this->maileonQueueResource->save($maileonQueue);
    }
}

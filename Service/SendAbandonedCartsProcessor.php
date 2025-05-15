<?php

namespace Xqueue\Maileon\Service;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Throwable;
use Xqueue\Maileon\Helper\AbandonedCartTransactionHelper;
use Xqueue\Maileon\Helper\TransactionHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Model\Maileon\TransactionService;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Xqueue\Maileon\Model\MaileonLogFactory;
use Magento\Reports\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Xqueue\Maileon\Model\ResourceModel\MaileonLog\CollectionFactory as MaileonLogCollectionFactory;
use Xqueue\Maileon\Model\ResourceModel\MaileonQueue\CollectionFactory as MaileonQueueCollectionFactory;
use Xqueue\Maileon\Model\ResourceModel\MaileonQueue as MaileonQueueResource;
use Xqueue\Maileon\Model\ResourceModel\MaileonLog as MaileonLogResource;
use Xqueue\Maileon\Model\MaileonQueue;

class SendAbandonedCartsProcessor
{
    public function __construct(
        private Logger $logger,
        private Config $config,
        private MaileonLogFactory $maileonLogFactory,
        private MaileonLogResource $maileonLogResource,
        private MaileonLogCollectionFactory $maileonLogCollectionFactory,
        private MaileonQueueCollectionFactory $maileonQueueCollectionFactory,
        private MaileonQueueResource $maileonQueueResource,
        private QuoteCollectionFactory $quoteCollectionFactory,
        private TransactionHelper $transactionHelper,
        private AbandonedCartTransactionHelper $abandonedCartTransactionHelper,
        private DateTime $dateTime
    ) {}

    public function execute(): array
    {
        $sentCart = 0;

        try {
            $queueCollection = $this->maileonQueueCollectionFactory->create();

            /** @var MaileonQueue $queueItem */
            foreach ($queueCollection as $queueItem) {
                $sentCart += $this->processQueueItem($queueItem);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Send abandoned cart error: ' . $exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        return [
            'sentCart' => $sentCart,
        ];
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception
     */
    protected function processQueueItem(MaileonQueue $queueItem): int
    {
        $storeId = (int) $queueItem->getStoreId();
        $config = $this->getSendAbandonedCartsConfig($storeId);
        $check = $this->checkSendAbandonedCartsConfigValues($config);
        $sentCart = 0;

        if (!$check['success']) {
            $this->logger->warning("Invalid config for store ID {$storeId}: {$check['message']}");
            return $sentCart;
        }

        if ($this->isAlreadySentWithSameContent($queueItem)) {
            $this->maileonQueueResource->delete($queueItem);
            return $sentCart;
        }

        $quote = $this->loadQuote($queueItem->getQuoteId());
        if (!$quote) {
            $this->logger->warning("Quote not found for ID {$queueItem->getQuoteId()}");
            return $sentCart;
        }

        if (! $this->transactionHelper->updateOrCreateContactFromMaileonQueue($queueItem)) {
            $this->logger->warning("Contact sync failed for quote ID {$queueItem->getQuoteId()}");
            return $sentCart;
        }

        $content = $this->abandonedCartTransactionHelper->createAbandonedCartTXContent($quote);

        $email = $this->resolveRecipientEmail($queueItem->getRecipientEmail(), $config['overrideEmail']);

        if ($this->sendAbandonedCartTransaction($content, $email, $storeId)) {
            $this->saveAbandonedCartToLog($queueItem, $content['cart.product_ids']);
            $this->maileonQueueResource->delete($queueItem);
            $sentCart = 1;
        } else {
            $this->logger->warning("Failed to send abandoned cart transaction for quote ID {$queueItem->getQuoteId()}");
        }

        if (!empty($config['shadowEmail'])) {
            $this->sendAbandonedCartTransaction($content, $config['shadowEmail'], $storeId);
        }

        return $sentCart;
    }

    /**
     * Get plugin config values
     *
     * @param integer|null $storeId
     * @return array
     */
    private function getSendAbandonedCartsConfig(?int $storeId = null): array
    {
        return [
            'moduleEnabled' => $this->config->isAbandonedCartEnabled($storeId),
            'apiKey'        => $this->config->getApiKey($storeId),
            'permission'    => $this->config->getAbandonedCartPermission($storeId),
            'shadowEmail'   => $this->config->getAbandonedCartShadowEmail($storeId),
            'overrideEmail' => $this->config->getAbandonedCartOverrideEmail($storeId),
        ];
    }

    /**
     * Validate config values
     *
     * @param array $config
     * @return array
     */
    private function checkSendAbandonedCartsConfigValues(array $config): array
    {
        if (empty($config['moduleEnabled']) || strtolower($config['moduleEnabled']) === 'no') {
            return [
                'success' => false,
                'message' => 'Module disabled! Value: ' . ($config['moduleEnabled'] ?? 'null')
            ];
        }

        $requiredKeys = [
            'apiKey'    => 'Maileon API key is empty!',
            'permission'=> 'Permission is empty!',
        ];

        foreach ($requiredKeys as $key => $errorMessage) {
            if (empty($config[$key])) {
                return [
                    'success' => false,
                    'message' => $errorMessage,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Every config value is OK!'
        ];
    }

    /**
     * Check if there is already sent transaction with the same metrics
     *
     * @param MaileonQueue $maileonQueue
     * @return boolean
     */
    protected function isAlreadySentWithSameContent(MaileonQueue $maileonQueue): bool
    {
        $collection = $this->maileonLogCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', $maileonQueue->getQuoteId());
        $collection->addFieldToFilter('quote_hash', $maileonQueue->getQuoteHash());
        $collection->setPageSize(1);

        return (bool) $collection->getSize();
    }

    /**
     * Send abandoned cart transaction to Maileon
     *
     * @param array $content
     * @param string $email
     * @return boolean
     * @throws Exception
     */
    protected function sendAbandonedCartTransaction(array $content, string $email, int $storeId): bool
    {
        $transactionService = new TransactionService(
            $this->config->getApiKey($storeId)
        );

        return $transactionService->sendTransaction(
            $email,
            Config::ABANDONED_CARTS_TX_NAME,
            $content
        );
    }

    /**
     * Save abandoned cart to Log table
     *
     * @param MaileonQueue $maileonQueue
     * @param string $productIds
     * @return void
     * @throws AlreadyExistsException
     */
    protected function saveAbandonedCartToLog(
        MaileonQueue $maileonQueue,
        string $productIds
    ): void {
        $log = $this->maileonLogFactory->create();
        $now = $this->dateTime->gmtDate();

        $log->setData([
            'sent_at'        => $now,
            'recipient_name' => $maileonQueue->getDataByKey('recipient_name'),
            'recipient_email'=> $maileonQueue->getDataByKey('recipient_email'),
            'product_ids'    => $productIds,
            'customer_id'    => $maileonQueue->getDataByKey('customer_id'),
            'sent_count'     => 1,
            'quote_id'       => $maileonQueue->getDataByKey('quote_id'),
            'store_id'       => $maileonQueue->getDataByKey('store_id'),
            'quote_hash'     => $maileonQueue->getDataByKey('quote_hash'),
            'updated_at'     => $now,
            'created_at'     => $now,
        ]);

        $this->maileonLogResource->save($log);
    }

    private function loadQuote(int $quoteId): ?Quote
    {
        /** @var Quote $quote */
        $quote = $this->quoteCollectionFactory->create()
            ->addFieldToFilter('entity_id', $quoteId)
            ->getFirstItem();

        return $quote && $quote->getId() ? $quote : null;
    }

    private function resolveRecipientEmail(string $originalEmail, ?string $overrideEmail): string
    {
        return !empty($overrideEmail) ? $overrideEmail : $originalEmail;
    }
}

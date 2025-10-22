<?php

namespace Xqueue\Maileon\Service;

use DateTimeImmutable;
use de\xqueue\maileon\api\client\contacts\Contact;
use de\xqueue\maileon\api\client\contacts\Contacts;
use de\xqueue\maileon\api\client\transactions\ContactReference;
use de\xqueue\maileon\api\client\transactions\Transaction;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Helper\ContactBuilder;
use Xqueue\Maileon\Helper\OrderTransactionHelper;
use Xqueue\Maileon\Model\Maileon\ImportService;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class OrderHistoryImportService
{
    private const BATCH_SIZE = 1000;

    private TransactionService $transactionService;

    public function __construct(
        private Config $config,
        private OrderCollectionFactory $orderCollectionFactory,
        private OrderTransactionHelper $orderTransactionHelper,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFactory $customerFactory,
        private ImportService $importService,
        private ContactBuilder $contactBuilder,
        private LoggerInterface $logger
    ) {
        $apiKey = $this->config->getApiKey();
        $this->transactionService = new TransactionService($apiKey);
    }

    /**
     * @throws Exception
     */
    public function importOrderHistory(
        array $storeIds,
        DateTimeImmutable $fromDt,
        ?DateTimeImmutable $toDt,
        ?ProgressBar $progress = null,
        int $page = 1
    ): int {
        $processed = 0;
        $currentPage = max(1, $page);

        $this->checkTransactionTypes(Config::ORDER_CONFIRM_TX_NAME);

        do {
            $collection = $this->buildCollection($storeIds, $fromDt, $toDt, $currentPage, self::BATCH_SIZE);

            if ($collection->count() === 0) {
                break;
            }

            $contacts = new Contacts();
            $orderTransactions = [];

            /** @var Order $order */
            foreach ($collection as $order) {
                try {
                    $contact = $this->buildContact($order);
                    $contacts->addContact($contact);

                    $transaction = $this->buildTransaction($order);
                    $orderTransactions[] = $transaction;

                    $processed++;

                    if ($progress) {
                        $progress->advance();
                    }
                } catch (Throwable $e) {
                    $this->logger->error('[Maileon import] Failed to send contact event for order', [
                        'orderId' => $order->getEntityId(),
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }

            try {
                if ($contacts->getCount() > 0) {
                    $this->importService->syncContacts($contacts);
                }

                if (!empty($orderTransactions)) {
                    $this->importService->sendTransactions($orderTransactions);
                }
            } catch (Throwable $e) {
                $this->logger->error('[Maileon import] Failed to send batch', [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }

            unset($contacts, $customerTransactions, $guestTransactions);
            gc_collect_cycles();

            $currentPage++;
        } while ($collection->count() === self::BATCH_SIZE);

        return $processed;
    }

    protected function buildCollection(
        array $storeIds,
        DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        int $page,
        int $pageSize
    ): Collection {
        $collection = $this->orderCollectionFactory->create();

        $collection->addFieldToFilter('created_at', ['gteq' => $from->format('Y-m-d H:i:s')]);
        if ($to !== null) {
            $collection->addFieldToFilter('created_at', ['lteq' => $to->format('Y-m-d H:i:s')]);
        }

        if (!empty($storeIds)) {
            $collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        $collection->setPageSize($pageSize)
            ->setCurPage($page)
            ->setOrder('created_at', 'ASC');

        return $collection;
    }

    /**
     * @throws Exception
     */
    protected function checkTransactionTypes(string $txName): void
    {
        if (!$this->transactionService->existsTransactionType($txName)) {
            $this->transactionService->setTransactionType($txName);
        }
    }

    public function countOrders(array $storeIds, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('created_at', [
            'from' => $from->format('Y-m-d H:i:s'),
            'to'   => $to->format('Y-m-d H:i:s'),
        ]);
        if (!empty($storeIds)) {
            $collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        return $collection->getSize();
    }

    protected function buildContact(Order $order): Contact
    {
        if ($order->getCustomerIsGuest()) {
            $contact = $this->contactBuilder->buildGuestCustomerContactObject($order);
        } else {
            $customerId = $order->getCustomerId();
            $customerData = $this->customerRepository->getById($customerId);
            $customer = $this->customerFactory->create();
            $customer->updateData($customerData);

            $contact = $this->contactBuilder->buildCustomerContactObject($customer);
        }

        return $contact;
    }

    /**
     * @throws Exception
     */
    protected function buildTransaction(Order $order): Transaction
    {
        $transaction = new Transaction();
        $transaction->contact = new ContactReference();
        $transaction->contact->email = $order->getCustomerEmail();
        $transaction->typeName = Config::ORDER_CONFIRM_TX_NAME;
        $transaction->content = $this->orderTransactionHelper->createOrderTXContent(
            $order,
            $this->transactionService
        );

        return $transaction;
    }
}

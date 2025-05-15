<?php

namespace Xqueue\Maileon\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Helper\OrderTransactionHelper;
use Xqueue\Maileon\Helper\TransactionHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class CreditMemoEmailObserver implements ObserverInterface
{
    public function __construct(
        private Config $config,
        private TransactionHelper $transactionHelper,
        private OrderTransactionHelper $orderTransactionHelper,
        private Logger $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            /** @var DataObject $transportObject */
            $transportObject = $observer->getEvent()->getData('transportObject');

            /** @var Order $order */
            $order = $transportObject->getDataByKey('order');
            $creditMemoId = $transportObject->getDataByKey('creditmemo_id');

            if (! $this->config->isCreditMemoTXEnabled($order->getStoreId())) {
                return;
            }

            if ($this->transactionHelper->updateOrCreateContactFromOrder($order)) {
                $transactionService = new TransactionService(
                    $this->config->getApiKey()
                );

                $content = $this->orderTransactionHelper->createOrderTXContent(
                    $order,
                    $transactionService
                );

                $content = $this->addStoreDataToTransactionContent($order, $creditMemoId, $content);

                $transactionService->sendTransaction(
                    $order->getCustomerEmail(),
                    Config::CREDIT_MEMO_TX_NAME,
                    $content
                );
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    protected function addStoreDataToTransactionContent(Order $order, string $creditMemoId, array $content): array
    {
        $content['order.creditmemo.id'] = $creditMemoId;
        $content['store.id']            = (string) ($order->getStoreId() ?? '');
        $content['store.name']          = (string) ($order->getStoreName() ?? '');

        return $content;
    }
}

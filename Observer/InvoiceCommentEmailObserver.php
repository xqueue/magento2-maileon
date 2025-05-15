<?php

namespace Xqueue\Maileon\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Helper\OrderTransactionHelper;
use Xqueue\Maileon\Helper\TransactionHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class InvoiceCommentEmailObserver implements ObserverInterface
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
            $comment = $transportObject->getDataByKey('comment');
            /** @var Invoice $invoice */
            $invoice = $transportObject->getDataByKey('invoice');

            if (! $this->config->isInvoiceTXEnabled($order->getStoreId())) {
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

                $content = $this->addStoreDataToTransactionContent($order, $comment, $invoice->getIncrementId(), $content);

                $transactionService->sendTransaction(
                    $order->getCustomerEmail(),
                    Config::ORDER_INVOICE_UPDATED_TX_NAME,
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

    protected function addStoreDataToTransactionContent(
        Order $order,
        string $comment,
        string $invoiceId,
        array $content
    ): array {
        $content['order.invoice.id'] = $invoiceId;
        $content['order.comment']    = $comment;
        $content['store.id']         = (string) ($order->getStoreId() ?? '');
        $content['store.name']       = (string) ($order->getStoreName() ?? '');

        return $content;
    }
}

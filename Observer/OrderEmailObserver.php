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

class OrderEmailObserver implements ObserverInterface
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

            if (! $this->config->isOrderConfirmationSendEnabled($order->getStoreId())) {
                return;
            }

            if ($this->transactionHelper->updateOrCreateContactFromOrder($order)) {
                $transactionService = new TransactionService(
                    $this->config->getApiKey()
                );

                $content = $this->orderTransactionHelper->createOrderTXContent(
                    $order,
                    $transactionService,
                    true
                );

                $content = $this->addStoreDataToTransactionContent($order, $content);

                $transactionService->sendTransaction(
                    $order->getCustomerEmail(),
                    Config::ORDER_CONFIRM_TX_NAME,
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

    protected function addStoreDataToTransactionContent(Order $order, array $content): array
    {
        $content['generic.string_1'] = (string) ($order->getStoreId() ?? '');
        $content['generic.string_2'] = (string) ($order->getStoreName() ?? '');

        return $content;
    }
}

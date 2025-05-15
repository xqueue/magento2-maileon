<?php

namespace Xqueue\Maileon\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Sales\Model\Order\Shipment\Track;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Magento\Sales\Model\Order\Shipment;
use Xqueue\Maileon\Helper\OrderTransactionHelper;
use Xqueue\Maileon\Helper\TransactionHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class ShipmentEmailObserver implements ObserverInterface
{
    public function __construct(
        private Config $config,
        private TransactionHelper $transactionHelper,
        private OrderTransactionHelper $orderTransactionHelper,
        private ShippingHelper $shippingHelper,
        private Logger $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            /** @var DataObject $transportObject */
            $transportObject = $observer->getEvent()->getData('transportObject');

            /** @var Order $order */
            $order = $transportObject->getDataByKey('order');
            /** @var Shipment $shipment */
            $shipment = $transportObject->getDataByKey('shipment');

            if (! $this->config->isShipmentStatusTXEnabled($order->getStoreId())) {
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

                $content = $this->addStoreDataToTransactionContent($order, $shipment, $content);

                $transactionService->sendTransaction(
                    $order->getCustomerEmail(),
                    Config::ORDER_SHIPMENT_TX_NAME,
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

    protected function addStoreDataToTransactionContent(Order $order, Shipment $shipment, array $content): array
    {
        $content['order.shipment.id'] = $shipment->getIncrementId();
        $content['store.id']          = (string) ($order->getStoreId() ?? '');
        $content['store.name']        = (string) ($order->getStoreName() ?? '');

        $tracksCollection = $shipment->getTracksCollection();

        if ($tracksCollection) {
            /** @var Track $trackItem */
            $trackItem = $tracksCollection->getLastItem();

            $content['shipping.service.id']            = $trackItem->getCarrierCode();
            $content['shipping.service.name']          = $trackItem->getTitle();
            if ($trackItem->getParentId()) {
                $content['shipping.service.tracking.url']  = $this->shippingHelper->getTrackingPopupUrlBySalesModel($trackItem);
            }
            $content['shipping.service.tracking.code'] = $trackItem->getNumber();
        }

        return $content;
    }
}
<?php

declare(strict_types=1);

namespace Xqueue\Maileon\Model\Order\Email;

use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender as MagentoCreditmemoSender;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as CreditmemoResource;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Area;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Email\Container\IdentityInterface;
use Magento\Sales\Model\Order\Email\Container\CreditmemoIdentity;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\App\Emulation;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;

class CreditmemoSender extends MaileonSender
{
    /**
     * @var IdentityInterface
     */
    protected $identityContainer;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Renderer
     */
    protected $addressRenderer;

    /**
     * @var \Xqueue\Maileon\Helper\External\Data
     */
    protected $maileonExternalDataHelper;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var CreditmemoResource
     */
    protected $creditmemoResource;

    /**
     * Application Event Dispatcher
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var Emulation
     */
    private $appEmulation;

    /**
     * @param CreditmemoIdentity $identityContainer
     * @param \Psr\Log\LoggerInterface $logger
     * @param Renderer $addressRenderer
     * @param \Xqueue\Maileon\Helper\External\Data $maileonExternalDataHelper
     * @param PaymentHelper $paymentHelper
     * @param CreditmemoResource $creditmemoResource
     * @param ManagerInterface $eventManager
     * @param Emulation $appEmulation
     */
    public function __construct(
        CreditmemoIdentity $identityContainer,
        \Psr\Log\LoggerInterface $logger,
        Renderer $addressRenderer,
        \Xqueue\Maileon\Helper\External\Data $maileonExternalDataHelper,
        PaymentHelper $paymentHelper,
        CreditmemoResource $creditmemoResource,
        ManagerInterface $eventManager,
        Emulation $appEmulation = null
    ) {
        parent::__construct($addressRenderer);
        $this->identityContainer = $identityContainer;
        $this->logger = $logger;
        $this->addressRenderer = $addressRenderer;
        $this->maileonExternalDataHelper = $maileonExternalDataHelper;
        $this->paymentHelper = $paymentHelper;
        $this->creditmemoResource = $creditmemoResource;
        $this->eventManager = $eventManager;
        $this->appEmulation = $appEmulation ?: ObjectManager::getInstance()->get(Emulation::class);
    }

    /**
     * Sends order email to the customer.
     *
     * @param MagentoCreditmemoSender $subject
     * @param callable $proceed
     * @param Creditmemo $creditmemo
     * @param bool $forceSyncMode
     */
    public function aroundSend(
        MagentoCreditmemoSender $subject,
        callable $proceed,
        Creditmemo $creditmemo,
        $forceSyncMode = false
    ) {
        if ($this->pluginConfig['creditmemo'] == 'yes') {
            $this->identityContainer->setStore($creditmemo->getStore());
            $creditmemo->setSendEmail($this->identityContainer->isEnabled());

            $order = $creditmemo->getOrder();
            $this->appEmulation->startEnvironmentEmulation($order->getStoreId(), Area::AREA_FRONTEND, true);
            $transport = [
                'order' => $order,
                'order_id' => $order->getId(),
                'creditmemo' => $creditmemo,
                'creditmemo_id' => $creditmemo->getId(),
                'comment' => $creditmemo->getCustomerNoteNotify() ? $creditmemo->getCustomerNote() : '',
                'billing' => $order->getBillingAddress(),
                'payment_html' => $this->getPaymentHtml($order),
                'store' => $order->getStore(),
                'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
                'formattedBillingAddress' => $this->getFormattedBillingAddress($order),
                'order_data' => [
                    'customer_name' => $order->getCustomerName(),
                    'is_not_virtual' => $order->getIsNotVirtual(),
                    'email_customer_note' => $order->getEmailCustomerNote(),
                    'frontend_status_label' => $order->getFrontendStatusLabel()
                ]
            ];
            $transportObject = new DataObject($transport);
            $this->appEmulation->stopEnvironmentEmulation();

            /**
             * Event argument `transport` is @deprecated. Use `transportObject` instead.
             */
            $this->eventManager->dispatch(
                'email_creditmemo_set_template_vars_before',
                ['sender' => $this, 'transport' => $transportObject->getData(), 'transportObject' => $transportObject]
            );

            if ($this->checkAndSend($order, $creditmemo)) {
                $creditmemo->setEmailSent(true);
                $this->creditmemoResource->saveAttribute($creditmemo, ['send_email', 'email_sent']);
            } else {
                $this->creditmemoResource->saveAttribute($creditmemo, 'send_email');
            }


            $result = null;
        } else {
            $result = $proceed($creditmemo, $forceSyncMode);
        }

        return $result;
    }

    /**
     * Send order email if it is enabled in configuration.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    protected function checkAndSend(Order $order, Creditmemo $creditmemo)
    {
        $this->identityContainer->setStore($order->getStore());

        if (!$this->identityContainer->isEnabled()) {
            return false;
        }

        $contactCreated = $this->updateOrCreateContact($order);

        if ($contactCreated) {
            $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');

            return $transactionCreate->sendTransaction(
                $order->getCustomerEmail(),
                'magento_order_creditmemo_v1',
                $this->createTransactionContent($order, $creditmemo)
            );
        }

        return false;
    }

    /**
     * Create the transaction content
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return array
     */
    protected function createTransactionContent(Order $order, Creditmemo $creditmemo): array
    {
        $content = [];

        $shippingAddressArr = $order->getShippingAddress()->getData();
        $billingAddressArr = $order->getBillingAddress()->getData();

        $totalNoShipping = $order->getGrandTotal() - $order->getShippingAmount();

        $content['order.id']                   = $order->getIncrementId();
        $content['order.date']                 = $order->getCreatedAt();
        $content['order.status']               = $order->getStatus();
        $content['order.total']                = (float) $this->formatPrice($order->getGrandTotal());
        $content['order.total_tax']            = (float) $this->formatPrice($order->getTaxAmount());
        $content['order.total_no_shipping']    = (float) $this->formatPrice($totalNoShipping);
        $content['order.currency']             = $order->getOrderCurrencyCode();
        $content['shipping.service.name']      = $order->getShippingMethod();
        $content['payment.method.id']          = $this->paymentMethodDetails($order)['id'];
        $content['payment.method.name']        = $this->paymentMethodDetails($order)['name'];
        $content['order.items']                = $this->createItems($order);
        $content['shipping.address.firstname'] = $shippingAddressArr['firstname'];
        $content['shipping.address.lastname']  = $shippingAddressArr['lastname'];
        $content['shipping.address.phone']     = $shippingAddressArr['telephone'];
        $content['shipping.address.region']    = $shippingAddressArr['region'];
        $content['shipping.address.city']      = $shippingAddressArr['city'];
        $content['shipping.address.zip']       = $shippingAddressArr['postcode'];
        $content['shipping.address.street']    = $shippingAddressArr['street'];
        $content['billing.address.firstname']  = $billingAddressArr['firstname'];
        $content['billing.address.lastname']   = $billingAddressArr['lastname'];
        $content['billing.address.phone']      = $billingAddressArr['telephone'];
        $content['billing.address.region']     = $billingAddressArr['region'];
        $content['billing.address.city']       = $billingAddressArr['city'];
        $content['billing.address.zip']        = $billingAddressArr['postcode'];
        $content['billing.address.street']     = $billingAddressArr['street'];
        $content['order.creditmemo.id']        = $creditmemo->getIncrementId();
        $content['store.id']                   = $order->getStoreId();
        $content['store.name']                 = $order->getStoreName();

        return $content;
    }

    /**
     * Create the ordered product items array
     *
     * @param Order $order
     * @return array
     */
    protected function createItems(Order $order): array
    {
        $orderedItems = $order->getAllItems();
        $items = [];

        if (empty($orderedItems)) {
            return $items;
        }

        foreach ($orderedItems as $orderedItem) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $product = $objectManager->create('Magento\Catalog\Model\Product')->load($orderedItem->getProductId());

            if (empty($product)) {
                $this->logger->error(
                    'Product not found!',
                    [
                        'Order' => $order->getId(),
                        'OrderItemId' => $orderedItem->getProductId()
                    ]
                );
                break;
            }

            $itemTotal = $this->formatPrice(
                $orderedItem->getPriceInclTax() * intval($orderedItem->getQtyOrdered())
            );

            $item['product_id'] =        $orderedItem->getProductId();
            $item['title'] =             $orderedItem->getName();
            $item['single_price'] =      $this->formatPrice($orderedItem->getPriceInclTax());
            $item['total'] =             $itemTotal;
            $item['sku'] =               $orderedItem->getSku();
            $item['quantity'] =          (int) $orderedItem->getQtyOrdered();
            $item['url'] =               $product->getProductUrl();
            $item['image_url'] =         $this->getProductImageUrl($product);
            $item['categories'] =        $this->getProductCategories($product);
            $item['short_description'] = $product->getShortDescription();

            // Get custom implementations of customer attributes for order product
            $customProductAttributes = $this->maileonExternalDataHelper->getCustomProductAttributes($item);

            foreach ($customProductAttributes as $key => $value) {
                $item[$key] = $value;
            }

            if (!empty((int) $itemTotal)) {
                array_push($items, $item);
            }
        }

        return $items;
    }

    /**
     * Get the payment method id and name
     *
     * @param Order $order
     * @return array
     */
    private function paymentMethodDetails(Order $order): array
    {
        $paymentMethodDetails = [
            'id' => '',
            'name' => ''
        ];

        $payment = $order->getPayment();

        if (!empty($payment)) {
            $paymentMethodDetails['id'] = $payment->getMethod();
            $paymentAdditionalInfo = $payment->getAdditionalInformation();

            if (!empty($paymentAdditionalInfo) && array_key_exists('method_title', $paymentAdditionalInfo)) {
                $paymentMethodDetails['name'] = $paymentAdditionalInfo['method_title'];
            }
        }

        return $paymentMethodDetails;
    }

    /**
     * Return payment info block as html
     *
     * @param Order $order
     * @return string
     * @throws \Exception
     */
    protected function getPaymentHtml(Order $order)
    {
        return $this->paymentHelper->getInfoBlockHtml(
            $order->getPayment(),
            $this->identityContainer->getStore()->getStoreId()
        );
    }
}

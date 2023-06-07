<?php

declare(strict_types=1);

namespace Xqueue\Maileon\Model\Order\Email;

use Magento\Sales\Model\Order\Email\Sender\OrderSender as MagentoOrderSender;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Container\IdentityInterface;
use Magento\Sales\Model\Order\Email\Container\OrderIdentity;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\DataObject;
use Xqueue\Maileon\Model\Maileon\ContactCreate;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;

class OrderSender extends MaileonSender
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
     * @var OrderResource
     */
    protected $orderResource;

    /**
     * Application Event Dispatcher
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @param OrderIdentity $identityContainer
     * @param \Psr\Log\LoggerInterface $logger
     * @param Renderer $addressRenderer
     * @param \Xqueue\Maileon\Helper\External\Data $maileonExternalDataHelper
     * @param PaymentHelper $paymentHelper
     * @param OrderResource $orderResource
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        OrderIdentity $identityContainer,
        \Psr\Log\LoggerInterface $logger,
        Renderer $addressRenderer,
        \Xqueue\Maileon\Helper\External\Data $maileonExternalDataHelper,
        PaymentHelper $paymentHelper,
        OrderResource $orderResource,
        ManagerInterface $eventManager,
    ) {
        parent::__construct($addressRenderer);
        $this->identityContainer = $identityContainer;
        $this->logger = $logger;
        $this->addressRenderer = $addressRenderer;
        $this->maileonExternalDataHelper = $maileonExternalDataHelper;
        $this->paymentHelper = $paymentHelper;
        $this->orderResource = $orderResource;
        $this->eventManager = $eventManager;
    }

    /**
     * Send email to customer
     *
     * @param MagentoOrderSender $subject
     * @param callable $proceed
     * @param Order $order
     * @param bool $notify
     * @param string $comment
     */
    public function aroundSend(
        MagentoOrderSender $subject,
        callable $proceed,
        Order $order,
        $forceSyncMode = false
    ) {
        if (!empty($this->pluginConfig['maileonApiKey']) && $this->pluginConfig['orderConfirm'] == 'yes') {
            $this->identityContainer->setStore($order->getStore());
            $order->setSendEmail($this->identityContainer->isEnabled());

            if ($this->checkAndSend($order)) {
                $order->setEmailSent(true);
                $this->orderResource->saveAttribute($order, ['send_email', 'email_sent']);
            } else {
                $this->orderResource->saveAttribute($order, 'send_email');
            }

            $result = true;
        } else {
            $result = $proceed($order, $forceSyncMode);
        }

        return $result;
    }

    /**
     * Send order email if it is enabled in configuration.
     *
     * @param Order $order
     * @param string $comment
     * @return bool
     */
    protected function checkAndSend(Order $order)
    {
        $this->identityContainer->setStore($order->getStore());

        if (!$this->identityContainer->isEnabled()) {
            return false;
        }
        $this->triggerEvent($order);

        $contactCreated = $this->updateOrCreateContact($order);

        if ($contactCreated) {
            $transactionCreate = new TransactionCreate(
                $this->pluginConfig['maileonApiKey'],
                $this->pluginConfig['printCurl']
            );

            $content = $this->createTransactionContent($order, $transactionCreate);

            $transactionCreate->sendTransaction(
                $order->getCustomerEmail(),
                'magento_orders_v2',
                $content
            );

            return true;
        }

        return false;
    }

    /**
     * Trigger "email_order_set_template_vars_before"  event
     *
     * @param Order $order
     * @return void
     */
    protected function triggerEvent(Order $order): void
    {
        $transport = [
            'order' => $order,
            'order_id' => $order->getId(),
            'billing' => $order->getBillingAddress(),
            'payment_html' => $this->getPaymentHtml($order),
            'store' => $order->getStore(),
            'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
            'formattedBillingAddress' => $this->getFormattedBillingAddress($order),
            'created_at_formatted' => $order->getCreatedAtFormatted(2),
            'order_data' => [
                'customer_name' => $order->getCustomerName(),
                'is_not_virtual' => $order->getIsNotVirtual(),
                'email_customer_note' => $order->getEmailCustomerNote(),
                'frontend_status_label' => $order->getFrontendStatusLabel()
            ]
        ];
        $transportObject = new DataObject($transport);

        /**
         * Event argument `transport` is @deprecated. Use `transportObject` instead.
         */
        $this->eventManager->dispatch(
            'email_order_set_template_vars_before',
            ['sender' => $this, 'transport' => $transportObject, 'transportObject' => $transportObject]
        );
    }

    /**
     * Update or create the contact at Maileon
     *
     * @param Order $order
     * @return boolean
     */
    protected function updateOrCreateContact(Order $order): bool
    {
        $contactCreate = new ContactCreate(
            $this->pluginConfig['maileonApiKey'],
            $order->getCustomerEmail(),
            'none',
            false,
            false,
            null,
            $this->pluginConfig['printCurl']
        );

        $standard_fields = array(
            'FIRSTNAME' => $order->getCustomerFirstname(),
            'LASTNAME' => $order->getCustomerLastname(),
            'FULLNAME' => $order->getCustomerName()
        );

        $custom_fields = array(
            'magento_storeview_id' => $order->getStoreId(),
            'magento_source' => 'transaction_create'
        );

        if (!$contactCreate->maileonContactIsExists()) {
            $contactCreate->setPermission($contactCreate->getPermission(
                $this->pluginConfig['buyersPermissionEnabled'],
                $this->pluginConfig['buyersTransactionPermission']
            ));

            $response = $contactCreate->makeMalieonContact(array(), $standard_fields, $custom_fields);

            if ($response) {
                $this->logger->info('Contact subscribe Done!');
            } else {
                $this->logger->error('Contact subscribe Failed!');
            }
        } else {
            $this->logger->info('Contact exists at Maileon.');
        }

        return true;
    }

    /**
     * Create the transaction content
     *
     * @param Order $order
     * @param TransactionCreate $transactionCreate
     * @return array
     */
    protected function createTransactionContent(Order $order, TransactionCreate $transactionCreate): array
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
        $content['order.items']                = $this->createItems($order, $transactionCreate);
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

        return $content;
    }

    /**
     * Create the ordered product items array
     *
     * @param Order $order
     * @param TransactionCreate $transactionCreate
     * @return array
     */
    protected function createItems(Order $order, TransactionCreate $transactionCreate): array
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

                $transactionCreate->sendTransaction(
                    $order->getCustomerEmail(),
                    'magento_orders_extended_v2',
                    $this->createExtendedTransactionContent($order, $item)
                );
            }
        }

        return $items;
    }

    /**
     * Create the extended transaction content (ordered item/transaction)
     *
     * @param Order $order
     * @param array $productItem
     * @return array
     */
    protected function createExtendedTransactionContent(Order $order, array $productItem): array
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
        $content['product.id']                 = $productItem['product_id'];
        $content['product.title']              = $productItem['title'];
        $content['product.single_price']       = $productItem['single_price'];
        $content['product.total']              = $productItem['total'];
        $content['product.sku']                = $productItem['sku'];
        $content['product.quantity']           = (string) $productItem['quantity'];
        $content['product.image_url']          = $productItem['image_url'];
        $content['product.url']                = $productItem['url'];
        $content['product.categories']         = $productItem['categories'];
        $content['product.short_description']  = $productItem['short_description'];
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

        // Get custom implementations of customer attributes for order extended transaction
        $customExtAttributes = $this->maileonExternalDataHelper->getCustomOrderExtendedTransactionAttributes(
            $content
        );

        foreach ($customExtAttributes as $key => $value) {
            $content[$key] = $value;
        }

        return $content;
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
     * Get payment info block as html
     *
     * @param Order $order
     * @return string
     */
    protected function getPaymentHtml(Order $order)
    {
        return $this->paymentHelper->getInfoBlockHtml(
            $order->getPayment(),
            $this->identityContainer->getStore()->getStoreId()
        );
    }
}

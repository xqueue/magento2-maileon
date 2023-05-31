<?php
namespace Xqueue\Maileon\Observer;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\ObserverInterface;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;
use Xqueue\Maileon\Model\Maileon\ContactCreate;

class AfterPlaceOrder implements ObserverInterface
{
    protected $_order;

    protected $_subscriber;

    protected $_customerRepositoryInterface;

    protected $helper;

    protected $storeManager;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Xqueue\Maileon\Helper\External\Data $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_order = $order;
        $this->_subscriber= $subscriber;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
    }

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $plugin_config['apikey'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/api_key', ScopeInterface::SCOPE_STORE);

        $plugin_config['print_curl'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/print_curl', ScopeInterface::SCOPE_STORE);

        $plugin_config['module_enabled'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/active_modul', ScopeInterface::SCOPE_STORE);

        $plugin_config['buyers_permission_enabled'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/buyers_permission_enabled', ScopeInterface::SCOPE_STORE);

        $plugin_config['buyers_transaction_permission'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/buyers_transaction_permission', ScopeInterface::SCOPE_STORE);

        if (!empty($plugin_config['apikey']) && $plugin_config['module_enabled'] == 'yes') {
            $orderids = $observer->getEvent()->getOrderIds();

            foreach ($orderids as $orderid) {
                $order = $this->_order->load($orderid);
            }

            $all_item = $order->getAllItems();
            $customer_id = $order->getCustomerId();

            // Shipping, billing address

            $shipping_address_obj = $order->getShippingAddress();
            $billing_address_obj = $order->getBillingAddress();

            $shipping_address_array = $shipping_address_obj->getData();
            $billing_address_array = $billing_address_obj->getData();

            if (!empty($customer_id)) {
                $customer = $this->_customerRepositoryInterface->getById($customer_id);

                $customer_email = $customer->getEmail();

                $customer_data = array(
                    'firstname' => $customer->getFirstname(),
                    'lastname' => $customer->getLastname(),
                    'fullname' => $customer->getFirstname() . ' ' . $customer->getLastname()
                );
            } else {
                $customer_email = $order->getCustomerEmail();

                $customer_data = array(
                    'firstname' => $shipping_address_array['firstname'],
                    'lastname' => $shipping_address_array['lastname'],
                    'fullname' => $shipping_address_array['firstname'] . ' ' . $shipping_address_array['lastname']
                );
            }

            $this->createContact(
                $plugin_config['apikey'],
                $customer_email,
                $plugin_config,
                $customer_data
            );

            $transactionCreate = new TransactionCreate($plugin_config['apikey'], $plugin_config['print_curl']);

            // Specify the content

            $order_total = number_format(
                doubleval($order->getGrandTotal()),
                2,
                '.',
                ''
            );

            $order_total_tax = number_format(
                doubleval($order->getTaxAmount()),
                2,
                '.',
                ''
            );

            $order_total_no_shipping = number_format(
                doubleval($order->getGrandTotal() - $order->getShippingAmount()),
                2,
                '.',
                ''
            );

            $payment = $order->getPayment();
            $payment_additional_data = $payment->getAdditionalInformation();

            $content['order.id']                = $order->getId();
            $content['order.date']              = $order->getCreatedAt();
            $content['order.status']            = $order->getStatus();
            $content['order.total']             = (float) $order_total;
            $content['order.total_tax']         = (float) $order_total_tax;
            $content['order.total_no_shipping'] = (float) $order_total_no_shipping;
            $content['order.currency']          = $order->getOrderCurrencyCode();
            $content['shipping.service.name']   = $order->getShippingMethod();
            $content['payment.method.id']       = $payment->getMethod();

            if (!empty($payment_additional_data) && array_key_exists('method_title', $payment_additional_data)) {
                $content['payment.method.name'] = $payment_additional_data['method_title'];
            }
            $items = array();

            // Product items

            foreach ($all_item as $item) {
                // Product data
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $product = $objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());
                $product_url = $product->getProductUrl();

                // Category data
                $category_ids = $product->getCategoryIds();
                $categories = [];
                 
                foreach ($category_ids as $category) {
                    $cat = $objectManager->create('Magento\Catalog\Model\Category')->load($category);
                    $categories[] = $cat->getName();
                }

                $product_categories = implode(',', $categories);

                // Image data
                $imagewidth = 200;
                $imageheight = 200;
                $imageHelper = $objectManager->get('\Magento\Catalog\Helper\Image');

                $image_url = $imageHelper->init(
                    $product,
                    'product_page_image_small'
                )->setImageFile($product->getFile())->resize($imagewidth, $imageheight)->getUrl();

                $item_total = number_format(
                    doubleval($item->getPriceInclTax() * intval($item->getQtyOrdered())),
                    2,
                    '.',
                    ''
                );

                $item_single_price = number_format(
                    doubleval($item->getPriceInclTax()),
                    2,
                    '.',
                    ''
                );

                $product_item['product_id']        = $item->getProductId();
                $product_item['title']             = $item->getName();
                $product_item['single_price']      = $item_single_price;
                $product_item['total']             = $item_total;
                $product_item['sku']               = $item->getSku();
                $product_item['quantity']          = (int) $item->getQtyOrdered();
                $product_item['url']               = $product_url;
                $product_item['image_url']         = $image_url;
                $product_item['categories']        = $product_categories;
                $product_item['short_description'] = $product->getShortDescription();

                // Get custom implementations of customer attributes for order product
                $customProductAttributes = $this->helper->getCustomProductAttributes($product_item);

                foreach ($customProductAttributes as $key => $value) {
                    $product_item[$key] = $value;
                }

                if (!empty((int) $item_total)) {
                    array_push($items, $product_item);
                }

                $content_ext['order.id']                   = $order->getId();
                $content_ext['order.date']                 = $order->getCreatedAt();
                $content_ext['order.status']               = $order->getStatus();
                $content_ext['order.total']                = (float) $order_total;
                $content_ext['order.total_tax']            = (float) $order_total_tax;
                $content_ext['order.total_no_shipping']    = (float) $order_total_no_shipping;
                $content_ext['order.currency']             = $order->getOrderCurrencyCode();
                $content_ext['shipping.service.name']      = $order->getShippingMethod();
                $content_ext['payment.method.id']          = $payment->getMethod();
            
                if (!empty($payment_additional_data) && array_key_exists('method_title', $payment_additional_data)) {
                    $content_ext['payment.method.name'] = $payment_additional_data['method_title'];
                }
                
                $content_ext['product.id']                 = $item->getProductId();
                $content_ext['product.title']              = $item->getName();
                $content_ext['product.single_price']       = $item_single_price;
                $content_ext['product.total']              = $item_total;
                $content_ext['product.sku']                = $item->getSku();
                $content_ext['product.quantity']           = (string) round($item->getQtyOrdered());
                $content_ext['product.image_url']          = $image_url;
                $content_ext['product.url']                = $product_url;
                $content_ext['product.categories']         = $product_categories;
                $content_ext['product.short_description']  = $product->getShortDescription();
                $content_ext['shipping.address.firstname'] = $shipping_address_array['firstname'];
                $content_ext['shipping.address.lastname']  = $shipping_address_array['lastname'];
                $content_ext['shipping.address.phone']     = $shipping_address_array['telephone'];
                $content_ext['shipping.address.region']    = $shipping_address_array['region'];
                $content_ext['shipping.address.city']      = $shipping_address_array['city'];
                $content_ext['shipping.address.zip']       = $shipping_address_array['postcode'];
                $content_ext['shipping.address.street']    = $shipping_address_array['street'];
                $content_ext['billing.address.firstname']  = $billing_address_array['firstname'];
                $content_ext['billing.address.lastname']   = $billing_address_array['lastname'];
                $content_ext['billing.address.phone']      = $billing_address_array['telephone'];
                $content_ext['billing.address.region']     = $billing_address_array['region'];
                $content_ext['billing.address.city']       = $billing_address_array['city'];
                $content_ext['billing.address.zip']        = $billing_address_array['postcode'];
                $content_ext['billing.address.street']     = $billing_address_array['street'];

                // Get custom implementations of customer attributes for order extended transaction
                $customExtAttributes = $this->helper->getCustomOrderExtendedTransactionAttributes(
                    $content_ext
                );

                foreach ($customExtAttributes as $key => $value) {
                    $content_ext[$key] = $value;
                }

                if (!empty((int) $item_total)) {
                    // Send the request
                    $transactionCreate->sendTransaction(
                        $customer_email,
                        'magento_orders_extended_v2',
                        $content_ext
                    );
                }
            }
            $content['order.items']                = $items;
            $content['shipping.address.firstname'] = $shipping_address_array['firstname'];
            $content['shipping.address.lastname']  = $shipping_address_array['lastname'];
            $content['shipping.address.phone']     = $shipping_address_array['telephone'];
            $content['shipping.address.region']    = $shipping_address_array['region'];
            $content['shipping.address.city']      = $shipping_address_array['city'];
            $content['shipping.address.zip']       = $shipping_address_array['postcode'];
            $content['shipping.address.street']    = $shipping_address_array['street'];
            $content['billing.address.firstname']  = $billing_address_array['firstname'];
            $content['billing.address.lastname']   = $billing_address_array['lastname'];
            $content['billing.address.phone']      = $billing_address_array['telephone'];
            $content['billing.address.region']     = $billing_address_array['region'];
            $content['billing.address.city']       = $billing_address_array['city'];
            $content['billing.address.zip']        = $billing_address_array['postcode'];
            $content['billing.address.street']     = $billing_address_array['street'];

            // Get custom implementations of customer attributes for order transaction
            $customAttributes = $this->helper->getCustomOrderTransactionAttributes($content);

            foreach ($customAttributes as $key => $value) {
                $content[$key] = $value;
            }

            // Send the request
            $transactionCreate->sendTransaction(
                $customer_email,
                'magento_orders_v2',
                $content
            );
        } else {
            $logger->info('Missing Maileon API key or module inactive!');
        }
    }

    private function createContact(
        $apikey,
        $email,
        $plugin_config,
        $customer_data
    ) {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
        $storeId = $this->storeManager->getStore()->getId();

        $contact_create = new ContactCreate(
            $apikey,
            $email,
            'none',
            false,
            false,
            null,
            $plugin_config['print_curl']
        );

        $standard_fields = array();

        $custom_fields = array(
            'magento_storeview_id' => $storeId,
            'magento_source' => 'order_confirmation'
        );

        if (!$contact_create->maileonContactIsExists()) {
            $contact_create->setPermission($contact_create->getPermission(
                $plugin_config['buyers_permission_enabled'],
                $plugin_config['buyers_transaction_permission']
            ));

            $response = $contact_create->makeMalieonContact($customer_data, $standard_fields, $custom_fields);

            if ($response) {
                $logger->info('Contact subscribe Done!');
            } else {
                $logger->error('Contact subscribe Failed!');
            }
        } else {
            $logger->info('Contact exists at Maileon.');
        }
    }
}

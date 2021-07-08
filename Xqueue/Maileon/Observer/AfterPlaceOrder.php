<?php
namespace Xqueue\Maileon\Observer;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\ObserverInterface;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\Transaction;
use de\xqueue\maileon\api\client\transactions\ContactReference;
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

        $apikey = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/api_key', ScopeInterface::SCOPE_STORE);

        $permission = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/permission', ScopeInterface::SCOPE_STORE);

        $doiprocess = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_process', ScopeInterface::SCOPE_STORE);

        $doiplusprocess = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_plus_process', ScopeInterface::SCOPE_STORE);

        $doikey = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/newsletter_settings/doi_key', ScopeInterface::SCOPE_STORE);

        $fallback_permission = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/permission', ScopeInterface::SCOPE_STORE);

        $print_curl = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/print_curl', ScopeInterface::SCOPE_STORE);

        $module_enabled = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/active_modul', ScopeInterface::SCOPE_STORE);

        if (!empty($apikey) && $module_enabled == 'yes') {
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
                $customer_data = array(
                    'firstname' => $customer->getFirstname(),
                    'lastname' => $customer->getLastname(),
                    'fullname' => $customer->getFirstname() . ' ' . $customer->getLastname()
                );
                $customer_email = $customer->getEmail();

                $checkSubscriber = $this->_subscriber->loadByCustomerId($customer_id);

                if ($checkSubscriber->isSubscribed()) {
                    $this->createContact(
                        $apikey,
                        $customer_email,
                        $permission,
                        $doiprocess,
                        $doiplusprocess,
                        $doikey,
                        $print_curl,
                        $customer_data
                    );
                } else {
                    $this->createContact(
                        $apikey,
                        $customer_email,
                        $fallback_permission,
                        'no',
                        'no',
                        null,
                        $print_curl,
                        $customer_data
                    );
                }
            } else {
                $customer_email = $order->getCustomerEmail();
                $customer_data = array(
                    'firstname' => $shipping_address_array['firstname'],
                    'lastname' => $shipping_address_array['lastname'],
                    'fullname' => $shipping_address_array['firstname'] . ' ' . $shipping_address_array['lastname']
                );

                $this->createContact(
                    $apikey,
                    $customer_email,
                    $fallback_permission,
                    'no',
                    'no',
                    null,
                    $print_curl,
                    $customer_data
                );
            }

            $maileon_config = array(
                'BASE_URI' => 'https://api.maileon.com/1.0',
                'API_KEY' => $apikey,
                'TIMEOUT' => 15
            );

            $transactionsService = new TransactionsService($maileon_config);

            if ($print_curl == 'yes') {
                $transactionsService->setDebug(true);
            } else {
                $transactionsService->setDebug(false);
            }

            $existsTransactionType = $transactionsService->getTransactionTypeByName('magento_orders_v2');
            $existsTransactionType_ext = $transactionsService->getTransactionTypeByName('magento_orders_extended_v2');

            $sync = new TransactionCreate($apikey, $print_curl);

            if ($existsTransactionType->getStatusCode() === 404) {
                $existsTransactionType = $sync->setTransactionType();
            }

            if ($existsTransactionType_ext->getStatusCode() === 404) {
                $existsTransactionType_ext = $sync->setTransactionTypeExtended();
            }

            // Create a transaction event and specify basic settings

            $transaction = new Transaction();
            $transaction->contact = new ContactReference();
            $transaction->contact->email = $customer_email;

            $transaction->typeName = 'magento_orders_v2';

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

            $transaction->content['order.id']                = $order->getId();
            $transaction->content['order.date']              = $order->getCreatedAt();
            $transaction->content['order.status']            = $order->getStatus();
            $transaction->content['order.total']             = (float) $order_total;
            $transaction->content['order.total_tax']         = (float) $order_total_tax;
            $transaction->content['order.total_no_shipping'] = (float) $order_total_no_shipping;
            $transaction->content['order.currency']          = $order->getOrderCurrencyCode();
            $transaction->content['shipping.service.name']   = $order->getShippingMethod();
            $items = array();

            // Product items

            foreach ($all_item as $item) {
                // Product data
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $product = $objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());
                $product_url = $product->getProductUrl();

                // Category data
                $category_ids = $product->getCategoryIds();
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

                $transaction_ext = new Transaction();
                $transaction_ext->contact = new ContactReference();
                $transaction_ext->contact->email = $customer_email;
                $transaction_ext->typeName = 'magento_orders_extended_v2';

                $transaction_ext->content['order.id']                   = $order->getId();
                $transaction_ext->content['order.date']                 = $order->getCreatedAt();
                $transaction_ext->content['order.status']               = $order->getStatus();
                $transaction_ext->content['order.total']                = (float) $order_total;
                $transaction_ext->content['order.total_tax']            = (float) $order_total_tax;
                $transaction_ext->content['order.total_no_shipping']    = (float) $order_total_no_shipping;
                $transaction_ext->content['order.currency']             = $order->getOrderCurrencyCode();
                $transaction_ext->content['shipping.service.name']      = $order->getShippingMethod();
                $transaction_ext->content['product.id']                 = $item->getProductId();
                $transaction_ext->content['product.title']              = $item->getName();
                $transaction_ext->content['product.single_price']       = $item_single_price;
                $transaction_ext->content['product.total']              = $item_total;
                $transaction_ext->content['product.sku']                = $item->getSku();
                $transaction_ext->content['product.quantity']           = (string) round($item->getQtyOrdered());
                $transaction_ext->content['product.image_url']          = $image_url;
                $transaction_ext->content['product.url']                = $product_url;
                $transaction_ext->content['product.categories']         = $product_categories;
                $transaction_ext->content['product.short_description']  = $product->getShortDescription();
                $transaction_ext->content['shipping.address.firstname'] = $shipping_address_array['firstname'];
                $transaction_ext->content['shipping.address.lastname']  = $shipping_address_array['lastname'];
                $transaction_ext->content['shipping.address.phone']     = $shipping_address_array['telephone'];
                $transaction_ext->content['shipping.address.region']    = $shipping_address_array['region'];
                $transaction_ext->content['shipping.address.city']      = $shipping_address_array['city'];
                $transaction_ext->content['shipping.address.zip']       = $shipping_address_array['postcode'];
                $transaction_ext->content['shipping.address.street']    = $shipping_address_array['street'];
                $transaction_ext->content['billing.address.firstname']  = $billing_address_array['firstname'];
                $transaction_ext->content['billing.address.lastname']   = $billing_address_array['lastname'];
                $transaction_ext->content['billing.address.phone']      = $billing_address_array['telephone'];
                $transaction_ext->content['billing.address.region']     = $billing_address_array['region'];
                $transaction_ext->content['billing.address.city']       = $billing_address_array['city'];
                $transaction_ext->content['billing.address.zip']        = $billing_address_array['postcode'];
                $transaction_ext->content['billing.address.street']     = $billing_address_array['street'];

                // Get custom implementations of customer attributes for order extended transaction
                $customExtAttributes = $this->helper->getCustomOrderExtendedTransactionAttributes(
                    $transaction_ext->content
                );

                foreach ($customExtAttributes as $key => $value) {
                    $transaction_ext->content[$key] = $value;
                }

                if (!empty((int) $item_total)) {
                    $transactions_ext = array($transaction_ext);

                    // Send the request
                    $response_ext = $transactionsService->createTransactions($transactions_ext, true, false);

                    $success_ext = $response_ext->isSuccess();

                    if (!$success_ext) {
                        $logger->debug('Maileon Transaction Save Data error (extended)!');
                    }
                }
            }
            $transaction->content['order.items']                = $items;
            $transaction->content['shipping.address.firstname'] = $shipping_address_array['firstname'];
            $transaction->content['shipping.address.lastname']  = $shipping_address_array['lastname'];
            $transaction->content['shipping.address.phone']     = $shipping_address_array['telephone'];
            $transaction->content['shipping.address.region']    = $shipping_address_array['region'];
            $transaction->content['shipping.address.city']      = $shipping_address_array['city'];
            $transaction->content['shipping.address.zip']       = $shipping_address_array['postcode'];
            $transaction->content['shipping.address.street']    = $shipping_address_array['street'];
            $transaction->content['billing.address.firstname']  = $billing_address_array['firstname'];
            $transaction->content['billing.address.lastname']   = $billing_address_array['lastname'];
            $transaction->content['billing.address.phone']      = $billing_address_array['telephone'];
            $transaction->content['billing.address.region']     = $billing_address_array['region'];
            $transaction->content['billing.address.city']       = $billing_address_array['city'];
            $transaction->content['billing.address.zip']        = $billing_address_array['postcode'];
            $transaction->content['billing.address.street']     = $billing_address_array['street'];

            // Get custom implementations of customer attributes for order transaction
            $customAttributes = $this->helper->getCustomOrderTransactionAttributes($transaction->content);

            foreach ($customAttributes as $key => $value) {
                $transaction->content[$key] = $value;
            }

            $transactions = array($transaction);

            // Send the request
            $response = $transactionsService->createTransactions($transactions, true, false);

            $success = $response->isSuccess();

            if (!$success) {
                $logger->debug('Maileon Transaction Save Data error!');
            } else {
                $logger->info('Maileon Transaction sync done!');
            }
        } else {
            $logger->debug('Missing Maileon API key or module inactive!');
        }
    }

    private function createContact(
        $apikey,
        $email,
        $permission,
        $doiprocess,
        $doiplusprocess,
        $doikey,
        $print_curl,
        $customer_data
    ) {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
        $storeId = $this->storeManager->getStore()->getId();

        $contact_create = new ContactCreate(
            $apikey,
            $email,
            $permission,
            $doiprocess,
            $doiplusprocess,
            $doikey,
            $print_curl
        );

        $standard_fields = array();

        $custom_fields = array(
            'magento_storeview_id' => $storeId,
            'magento_source' => 'order_confirmation'
        );

        if (!$contact_create->maileonContactIsExists()) {
            $response = $contact_create->makeMalieonContact($customer_data, $standard_fields, $custom_fields);

            if ($response) {
                $logger->info('Contact subscribe Done!');
            } else {
                $logger->debug('Contact subscribe Failed!');
            }
        }
    }
}

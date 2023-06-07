<?php

declare(strict_types=1);

namespace Xqueue\Maileon\Model\Order\Email;

use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order;
use Magento\Catalog\Model\Product;
use Magento\Sales\Model\Order\Address\Renderer;
use Xqueue\Maileon\Model\Maileon\ContactCreate;

abstract class MaileonSender
{
    /**
     * @var array
     */
    protected $pluginConfig;

    /**
     * @var Renderer
     */
    protected $addressRenderer;


    /**
     * @param Renderer $addressRenderer
     */
    public function __construct(
        Renderer $addressRenderer
    ) {
        $this->pluginConfig = $this->getPluginConfigValues();
        $this->addressRenderer = $addressRenderer;
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
            false
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

        return $contactCreate->makeMalieonContact(array(), $standard_fields, $custom_fields);
    }

    /**
     * Render shipping address into html.
     *
     * @param Order $order
     * @return string|null
     */
    protected function getFormattedShippingAddress($order)
    {
        return $order->getIsVirtual()
            ? null
            : $this->addressRenderer->format($order->getShippingAddress(), 'html');
    }

    /**
     * Render billing address into html.
     *
     * @param Order $order
     * @return string|null
     */
    protected function getFormattedBillingAddress($order)
    {
        return $this->addressRenderer->format($order->getBillingAddress(), 'html');
    }

    /**
     * Format price value
     *
     * @param mixed $price
     * @return string
     */
    protected function formatPrice(mixed $price): string
    {
        return number_format(
            doubleval($price),
            2,
            '.',
            ''
        );
    }

    /**
     * Get the product categories list
     *
     * @param Product $product
     * @return string
     */
    protected function getProductCategories(Product $product): string
    {
        $categoryIds = $product->getCategoryIds();
        $productCategories = '';

        if (!empty($categoryIds)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $categories = [];
            
            foreach ($categoryIds as $categoryId) {
                $category = $objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
                $categories[] = $category->getName();
            }

            $productCategories = implode(',', $categories);
        }

        return $productCategories;
    }

    /**
     * Get the product image url
     *
     * @param Product $product
     * @return string
     */
    protected function getProductImageUrl(Product $product): string
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $imagewidth = 200;
        $imageheight = 200;
        $imageHelper = $objectManager->get('\Magento\Catalog\Helper\Image');

        try {
            $imageUrl = $imageHelper->init(
                $product,
                'product_page_image_small'
            )->setImageFile($product->getFile())->resize($imagewidth, $imageheight)->getUrl();
        } catch (\Exception $e) {
            $imageUrl = '';
        }
        
        return $imageUrl;
    }

    /**
     * Get the plugin config values
     *
     * @return array
     */
    private function getPluginConfigValues(): array
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $config = [];

        $config['maileonApiKey'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/api_key', ScopeInterface::SCOPE_STORE);

        $config['printCurl'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/general/print_curl', ScopeInterface::SCOPE_STORE);

        $config['creditmemo'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/order_related_transactions/creditmemo', ScopeInterface::SCOPE_STORE);

        $config['invoice'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/order_related_transactions/invoice', ScopeInterface::SCOPE_STORE);

        $config['orderStatus'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/order_related_transactions/order_status', ScopeInterface::SCOPE_STORE);

        $config['shipmentStatus'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/order_related_transactions/shipment_status', ScopeInterface::SCOPE_STORE);

        $config['orderConfirm'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/active_modul', ScopeInterface::SCOPE_STORE);

        $config['buyersPermissionEnabled'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/buyers_permission_enabled', ScopeInterface::SCOPE_STORE);

        $config['buyersTransactionPermission'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/orders/buyers_transaction_permission', ScopeInterface::SCOPE_STORE);

        return $config;
    }
}

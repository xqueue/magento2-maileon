<?php

namespace Xqueue\Maileon\Helper;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Helper\External\Data as MaileonExternalData;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class OrderTransactionHelper extends AbstractTransactionHelper
{
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        ImageHelper $imageHelper,
        protected MaileonExternalData $maileonExternalDataHelper,
        protected ProductRepositoryInterface $productRepository,
        protected Logger $logger
    ) {
        parent::__construct($categoryRepository, $imageHelper);
    }

    /**
     * @throws Exception
     */
    public function createOrderTXContent(
        Order $order,
        TransactionService $transactionCreate,
        bool $sendPerProduct = false
    ): array {
        $content = [];

        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        $orderItems = $this->createItems($order, $transactionCreate, $sendPerProduct);
        $paymentDetails = $this->paymentMethodDetails($order);
        $totalNoShipping = $order->getGrandTotal() - $order->getShippingAmount();

        // Order
        $content['order.id']                = $order->getIncrementId();
        $content['order.date']              = (string) $order->getCreatedAt() ?: '';
        $content['order.status']            = (string) $order->getStatus() ?: '';
        $content['order.product_ids']       = $this->sanitizeProductIdList($orderItems['productIds']);
        $content['order.categories']        = $this->sanitizeCategoriesList($orderItems['categories']);
        $content['order.total']             = (float) $this->formatPrice($order->getGrandTotal());
        $content['order.total_tax']         = (float) $this->formatPrice($order->getTaxAmount());
        $content['order.total_no_shipping'] = (float) $this->formatPrice($totalNoShipping);
        $content['order.currency']          = (string) $order->getOrderCurrencyCode() ?: '';
        $content['order.items']             = $orderItems['items'];

        // Payment
        $content['payment.method.id']   = $paymentDetails['id'];
        $content['payment.method.name'] = $paymentDetails['name'];

        // Shipping
        $content['shipping.service.name'] = (string) $order->getShippingMethod() ?: '';
        $content += $this->extractAddressData($shippingAddress, 'shipping');

        // Billing
        $content += $this->extractAddressData($billingAddress, 'billing');

        // Custom attributes
        $customAttributes = $this->maileonExternalDataHelper->getCustomOrderTransactionAttributes($content);
        foreach ($customAttributes as $key => $value) {
            $content[$key] = $value;
        }

        return $content;
    }

    protected function extractAddressData(?Order\Address $address, string $prefix): array
    {
        if (!$address) {
            return [];
        }

        return [
            "{$prefix}.address.firstname" => $address->getFirstname(),
            "{$prefix}.address.lastname"  => $address->getLastname(),
            "{$prefix}.address.phone"     => $address->getTelephone(),
            "{$prefix}.address.region"    => $address->getRegion(),
            "{$prefix}.address.city"      => $address->getCity(),
            "{$prefix}.address.zip"       => $address->getPostcode(),
            "{$prefix}.address.street"    => implode(', ', $address->getStreet()),
        ];
    }

    /**
     * @throws Exception
     */
    protected function createItems(
        Order $order,
        TransactionService $transactionCreate,
        bool $sendPerProduct = false
    ): array {
        $items = [];
        $productIds = [];
        $categories = [];

        foreach ($order->getAllVisibleItems() as $orderedItem) {
            $productId = $orderedItem->getProductId();

            try {
                /** @var Product $product */
                $product = $this->productRepository->getById($productId);
            } catch (NoSuchEntityException) {
                $this->logger->error('Product not found!', [
                    'order_id' => $order->getId(),
                    'product_id' => $productId,
                ]);
                continue;
            }

            $item = $this->createItemData($orderedItem, $product);

            $productIds[] = $productId;
            $categories[] = $this->getProductCategories($product);
            $items[] = $item;

            if ($sendPerProduct) {
                $this->sendItemTransaction($order, $item, $transactionCreate);
            }
        }

        return [
            'items' => $items,
            'categories' => implode(',', array_unique($categories)),
            'productIds' => $productIds,
        ];
    }

    protected function createItemData(OrderItemInterface $item, Product $product): array
    {
        $quantity = (int) $item->getQtyOrdered();
        $priceInclTax = (float) $item->getPriceInclTax();
        $total = $this->formatPrice($priceInclTax * $quantity);

        $data = [
            'product_id'        => $item->getProductId(),
            'title'             => $item->getName(),
            'single_price'      => $this->formatPrice($priceInclTax),
            'total'             => $total,
            'sku'               => $item->getSku(),
            'quantity'          => $quantity,
            'url'               => $product->getProductUrl(),
            'image_url'         => htmlspecialchars($this->getProductImageUrl($product), ENT_QUOTES, "UTF-8"),
            'categories'        => $this->getProductCategories($product),
            'short_description' => $product->getData('short_description'),
        ];

        return array_merge(
            $data,
            $this->maileonExternalDataHelper->getCustomProductAttributes($data)
        );
    }

    /**
     * @throws Exception
     */
    protected function sendItemTransaction(Order $order, array $item, TransactionService $transactionCreate): void
    {
        $transactionCreate->sendTransaction(
            $order->getCustomerEmail(),
            Config::ORDERED_PRODUCTS_TX_NAME,
            $this->createExtendedTransactionContent($order, $item)
        );
    }

    protected function createExtendedTransactionContent(Order $order, array $productItem): array
    {
        $payment = $this->paymentMethodDetails($order);
        $totalNoShipping = $order->getGrandTotal() - $order->getShippingAmount();

        $content = [
            // Order
            'order.id'                => $order->getIncrementId(),
            'order.date'              => (string) $order->getCreatedAt() ?: '',
            'order.status'            => (string) $order->getStatus() ?: '',
            'order.total'             => (float) $this->formatPrice($order->getGrandTotal()),
            'order.total_tax'         => (float) $this->formatPrice($order->getTaxAmount()),
            'order.total_no_shipping' => (float) $this->formatPrice($totalNoShipping),
            'order.currency'          => (string) $order->getOrderCurrencyCode() ?: '',

            // Shipping
            'shipping.service.name'   => (string) $order->getShippingMethod() ?: '',

            // Payment
            'payment.method.id'       => $payment['id'],
            'payment.method.name'     => $payment['name'],

            // Product
            'product.id'              => $productItem['product_id'] ?? '',
            'product.title'           => $productItem['title'] ?? '',
            'product.single_price'    => $productItem['single_price'] ?? '',
            'product.total'           => $productItem['total'] ?? '',
            'product.sku'             => $productItem['sku'] ?? '',
            'product.quantity'        => (string) ($productItem['quantity'] ?? ''),
            'product.image_url'       => $this->sanitizeTransactionStringValue($productItem['image_url'] ?? ''),
            'product.url'             => $this->sanitizeTransactionStringValue($productItem['url'] ?? ''),
            'product.categories'      => $this->sanitizeTransactionStringValue($productItem['categories'] ?? ''),
            'product.short_description' => $this->sanitizeTransactionStringValue($productItem['short_description'] ?? ''),

            // Store info
            'generic.string_1'        => (string) ($order->getStoreId() ?? ''),
            'generic.string_2'        => (string) ($order->getStoreName() ?? ''),
        ];

        // Address sections
        $content += $this->extractAddressData($order->getShippingAddress(), 'shipping');
        $content += $this->extractAddressData($order->getBillingAddress(), 'billing');

        // Custom attributes
        $customExtAttributes = $this->maileonExternalDataHelper
            ->getCustomOrderExtendedTransactionAttributes($content);

        foreach ($customExtAttributes as $key => $value) {
            $content[$key] = $value;
        }

        return $content;
    }

    protected function paymentMethodDetails(Order $order): array
    {
        $payment = $order->getPayment();

        if (!$payment) {
            return ['id' => '', 'name' => ''];
        }

        $methodId = $payment->getMethod() ?? '';
        $methodName = $payment->getAdditionalInformation()['method_title'] ?? '';

        return [
            'id' => $methodId,
            'name' => $methodName,
        ];
    }
}

<?php

namespace Xqueue\Maileon\Helper;

use Magento\Framework\App\State;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\App\Emulation;
use Xqueue\Maileon\Helper\External\Data as MaileonExternalData;
use Xqueue\Maileon\Logger\Logger;

class AbandonedCartTransactionHelper extends AbstractTransactionHelper
{
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        ImageHelper $imageHelper,
        protected MaileonExternalData $maileonExternalDataHelper,
        protected ProductRepositoryInterface $productRepository,
        protected State $appState,
        protected Emulation $appEmulation,
        protected Logger $logger
    ) {
        parent::__construct($categoryRepository, $imageHelper);
    }

    public function createAbandonedCartTXContent(Quote $quote): array {
        $content = [];

        $cartItems = $this->createItems($quote);

        // Quote
        $content['cart.id']          = $quote->getEntityId();
        $content['cart.date']        = $quote->getUpdatedAt();
        $content['cart.items']       = $cartItems['items'];
        $content['cart.product_ids'] = $this->sanitizeProductIdList($cartItems['productIds']);
        $content['cart.categories']  = $this->sanitizeCategoriesList($cartItems['categories']);
        $content['cart.total']       = (float) $this->formatPrice($quote->getGrandTotal());
        $content['cart.total_tax']   = (float) $this->formatPrice($quote->getGrandTotal() - $quote->getSubtotal());
        $content['cart.currency']    = $quote->getBaseCurrencyCode();

        // Customer
        $fullname = $quote->getCustomerFirstname() . $quote->getCustomerLastname();
        $content['customer.salutation'] = $quote->getCustomerPrefix();
        $content['customer.full_name'] = $fullname;
        $content['customer.firstname'] = $quote->getCustomerFirstname();
        $content['customer.lastname'] = $quote->getCustomerLastname();
        $content['customer.id'] = $quote->getCustomerId();

        // Store info
        $content['generic.string_1'] = $quote->getStoreId() !== null ? (string) $quote->getStoreId() : '';
        $content['generic.string_2'] = $quote->getStore() ? $quote->getStore()->getName() : '';

        // Custom attributes
        $customAttributes = $this->maileonExternalDataHelper->getCustomAbandonedCartTransactionAttributes($content);
        foreach ($customAttributes as $key => $value) {
            $content[$key] = $value;
        }

        return $content;
    }

    /**
     * @throws LocalizedException
     */
    protected function createItems(Quote $quote): array {
        $items = [];
        $productIds = [];
        $categories = [];
        $quoteItems = $quote->getAllVisibleItems();

        // Emulate the frontend for the correct image urls, that need only in API
        if ($this->appState->getAreaCode() === null) {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        }
        $this->appEmulation->startEnvironmentEmulation(
            $quote->getStoreId(),
            Area::AREA_FRONTEND,
            true
        );

        foreach ($quoteItems as $quoteItem) {
            $productId = $quoteItem->getProductId();

            try {
                /** @var Product $product */
                $product = $this->productRepository->getById($productId);
            } catch (NoSuchEntityException) {
                $this->logger->error('Product not found!', [
                    'quote_id' => $quoteItem->getId(),
                    'product_id' => $productId,
                ]);
                continue;
            }

            $item = $this->createItemData($quoteItem, $product);

            $productIds[] = $productId;
            $categories[] = $this->getProductCategories($product);
            $items[] = $item;
        }

        // End emulation
        $this->appEmulation->stopEnvironmentEmulation();

        return [
            'items' => $items,
            'categories' => implode(',', array_unique($categories)),
            'productIds' => $productIds,
        ];
    }

    protected function createItemData(CartItemInterface $item, Product $product): array
    {
        $data = [
            'product_id'        => $item->getItemId(),
            'title'             => $item->getName(),
            'single_price'      => $this->formatPrice($item->getPriceInclTax()),
            'total'             => $this->formatPrice($item->getPriceInclTax() * intval($item->getQty())),
            'sku'               => $item->getSku(),
            'quantity'          => (int) $item->getQty(),
            'url'               => $product->getProductUrl(),
            'image_url'         => htmlspecialchars($this->getProductImageUrl($product), ENT_QUOTES, "UTF-8"),
            'thumbnail_url'     => htmlspecialchars($this->getProductThumbnailUrl($product), ENT_QUOTES, "UTF-8"),
            'categories'        => $this->getProductCategories($product),
            'short_description' => $product->getData('short_description'),
        ];

        return array_merge(
            $data,
            $this->maileonExternalDataHelper->getCustomProductAttributes($data)
        );
    }
}
<?php

namespace Xqueue\Maileon\Helper;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Wishlist\Model\Wishlist;
use Xqueue\Maileon\Logger\Logger;

class WishlistTransactionHelper extends AbstractTransactionHelper
{
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        ImageHelper $imageHelper,
        protected Logger $logger
    ) {
        parent::__construct($categoryRepository, $imageHelper);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function createWishlistAddedTXContent(
        Wishlist $wishlist,
        Product $product,
        Customer $customer
    ): array {
        $content = [
            'store_id' => (string) $wishlist->getStore()?->getId() ?? '',
            'store_name' => (string) $wishlist->getStore()?->getName() ?? '',
        ];

        $content = $this->addCustomerData($content, $customer);
        $content = $this->addProductData($content, $product, $wishlist->getStore());

        return $content;
    }

    protected function addCustomerData(array $content, Customer $customer): array
    {
        $content['customer.id']         = $customer->getId();
        $content['customer.salutation'] = $customer->getPrefix() ?? '';
        $content['customer.firstname']  = $customer->getFirstname();
        $content['customer.lastname']   = $customer->getLastname();
        $content['customer.fullname']   = trim(
            ($customer->getFirstName()) . ' ' . ($customer->getLastName())
        );

        $billingAddress = $customer->getDefaultBillingAddress();
        if ($billingAddress) {
            $content['customer.address.zip']     = $billingAddress->getPostcode() ?? '';
            $content['customer.address.city']    = $billingAddress->getCity() ?? '';
            $street = $billingAddress->getStreet();
            $content['customer.address.street']  = is_array($street) ? ($street[0] ?? '') : ($street ?? '');
            $content['customer.address.state']   = $billingAddress->getRegion() ?? '';
            $content['customer.address.country'] = $billingAddress->getCountryId() ?? '';
        }

        return $content;
    }

    protected function addProductData(array $content, Product $product, ?Store $store): array
    {
        $content['product.sku'] = $product->getSku();
        $content['product.id'] = $product->getId();
        $content['product.title'] = $product->getName() ?? '';
        $content['product.description'] = $product->getData('short_description');
        $content['product.url'] = $product->getProductUrl();
        $content['product.image_url'] = htmlspecialchars($this->getProductImageUrl($product), ENT_QUOTES, "UTF-8");
        $content['product.gross_price'] = round($product->getFinalPrice(), 2);
        $content['product.currency_symbol'] = $store?->getCurrentCurrencyCode() ?? '';
        $content['product.categories'] = $this->getProductCategories($product);

        return $content;
    }
}


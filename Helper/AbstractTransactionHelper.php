<?php

namespace Xqueue\Maileon\Helper;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Throwable;

abstract class AbstractTransactionHelper
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepository,
        protected ImageHelper $imageHelper
    ) {}

    protected function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    protected function getProductCategories(Product $product): string
    {
        $categoryNames = [];

        foreach ($product->getCategoryIds() as $categoryId) {
            try {
                $category = $this->categoryRepository->get($categoryId);
                $categoryNames[] = $category->getName();
            } catch (NoSuchEntityException) {
                // skip invalid category
            }
        }

        return implode(', ', $categoryNames);
    }

    protected function getProductImageUrl(Product $product): string
    {
        try {
            return $this->imageHelper->init($product, 'product_page_image_small')
                ->resize(200, 200)
                ->getUrl();
        } catch (Throwable) {
            return '';
        }
    }

    protected function getProductThumbnailUrl(Product $product): string
    {
        try {
            return $this->imageHelper
                ->init($product, 'product_thumbnail_image')
                ->getUrl();
        } catch (Throwable) {
            return '';
        }
    }

    protected function sanitizeCategoriesList(string $categoriesList): string
    {
        if (trim($categoriesList) === '') {
            return '';
        }

        $uniqueCategories = array_unique(
            array_filter(
                array_map('trim', explode(',', $categoriesList))
            )
        );

        return $this->sanitizeTransactionStringValue(implode(',', $uniqueCategories));
    }

    protected function sanitizeProductIdList(array $productIds): string
    {
        if (!$productIds) {
            return '';
        }

        $uniqueIds = array_unique(
            array_filter(
                array_map('trim', $productIds)
            )
        );

        return $this->sanitizeTransactionStringValue(implode(',', $uniqueIds));
    }

    protected function sanitizeTransactionStringValue(?string $value): string
    {
        return trim($value) !== '' ? mb_substr($value, 0, 1000) : '';
    }
}
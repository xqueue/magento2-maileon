<?php

namespace Xqueue\Maileon\Observer;

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Wishlist\Model\Wishlist;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Helper\TransactionHelper;
use Xqueue\Maileon\Helper\WishlistTransactionHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class WishlistAddProductObserver implements ObserverInterface
{
    public function __construct(
        private Config $config,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFactory $customerFactory,
        private WishlistTransactionHelper $wishlistTransactionHelper,
        private TransactionHelper $transactionHelper,
        private Logger $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            /** @var Wishlist $wishlist */
            $wishlist = $observer->getEvent()->getData('wishlist');
            /** @var Product $product */
            $product = $observer->getEvent()->getData('product');

            if (! $this->config->isWishlistProductAddTXEnabled($wishlist->getStore()->getStoreId())) {
                return;
            }

            $customer = $this->getCustomer($wishlist->getCustomerId());

            if ($this->transactionHelper->updateOrCreateContactFromCustomer($customer)) {
                $transactionService = new TransactionService(
                    $this->config->getApiKey()
                );

                $content = $this->wishlistTransactionHelper->createWishlistAddedTXContent(
                    $wishlist,
                    $product,
                    $customer
                );

                $transactionService->sendTransaction(
                    $customer->getEmail(),
                    Config::WISHLIST_PRODUCT_ADDED_TX_NAME,
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

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getCustomer(int $customerId): Customer
    {
        $customerData = $this->customerRepository->getById($customerId);
        $customer = $this->customerFactory->create();
        $customer->updateData($customerData);

        return $customer;
    }
}
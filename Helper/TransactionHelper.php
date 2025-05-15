<?php

namespace Xqueue\Maileon\Helper;

use de\xqueue\maileon\api\client\contacts\Permission;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Throwable;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Model\Maileon\ContactService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Xqueue\Maileon\Model\MaileonQueue;

class TransactionHelper
{
    public function __construct(
        private Config $config,
        private ContactBuilder $contactBuilder,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFactory $customerFactory,
        private Logger $logger
    ) {}

    public function updateOrCreateContactFromOrder(Order $order): bool
    {
        $storeId = (int) $order->getStoreId();
        $email = $order->getCustomerEmail();

        try {
            $contactService = $this->prepareContactService($email, $storeId);

            if ($order->getCustomerIsGuest()) {
                $contact = $this->contactBuilder->buildGuestCustomerContactObject($order);
            } else {
                $customerId = $order->getCustomerId();

                if (!$customerId) {
                    $this->logger->warning("Order has no customer ID, but is not marked as guest. Order ID: {$order->getId()}");
                    return false;
                }

                $customerData = $this->customerRepository->getById($customerId);
                $customer = $this->customerFactory->create();
                $customer->updateData($customerData);

                $contact = $this->contactBuilder->buildCustomerContactObject($customer);
            }

            $permissionCode = $contactService->getPermission(
                $this->config->isBuyersPermissionEnabled($storeId),
                $this->config->getBuyersPermission($storeId)
            );

            $contact->permission = Permission::getPermission($permissionCode);

            return $contactService->createOrUpdateMaileonContact($contact);
        } catch (Throwable $e) {
            $this->logger->error('Failed to create/update contact from Order: ' . $e->getMessage(), [
                'order_id' => $order->getId(),
                'customer_email' => $email,
            ]);
            return false;
        }
    }

    public function updateOrCreateContactFromCustomer(Customer $customer, ?string $newEmail = null): bool
    {
        $storeId = (int) $customer->getStoreId();
        $email = $newEmail ?? $customer->getEmail();

        try {
            $contactService = $this->prepareContactService($email, $storeId);

            $contact = $this->contactBuilder->buildCustomerContactObject($customer);
            $contact->permission = Permission::getPermission('none');

            return $contactService->createOrUpdateMaileonContact($contact);
        } catch (Throwable $e) {
            $this->logger->error('Failed to update/create Maileon contact from Customer: ' . $e->getMessage(), [
                'customer_id' => $customer->getId(),
                'email' => $email,
            ]);
            return false;
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function updateOrCreateContactFromMaileonQueue(MaileonQueue $queue): bool
    {
        $storeId = (int) $queue->getStoreId();
        $email = $queue->getRecipientEmail();

        try {
            $contactService = $this->prepareContactService($email, $storeId);
            $customerId = $queue->getCustomerId();

            $customerData = $this->customerRepository->getById($customerId);
            $customer = $this->customerFactory->create();
            $customer->updateData($customerData);

            $contact = $this->contactBuilder->buildCustomerContactObject($customer);

            $overrideEmail = $this->config->getAbandonedCartOverrideEmail($storeId);
            if (!empty($overrideEmail)) {
                $contact->email = $overrideEmail;
            }

            $contact->permission = Permission::$NONE;

            return $contactService->createOrUpdateMaileonContact($contact);
        } catch (Throwable $e) {
            $this->logger->error('Failed to create/update contact from Maileon queue: ' . $e->getMessage(), [
                'queue_id' => $queue->getId(),
                'store_id' => $storeId,
                'email' => $email,
            ]);
            return false;
        }
    }

    private function prepareContactService(string $email, int $storeId): ContactService
    {
        return new ContactService(
            $this->config->getApiKey($storeId),
            $email,
            false,
            false
        );
    }
}

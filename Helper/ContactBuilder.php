<?php

namespace Xqueue\Maileon\Helper;

use de\xqueue\maileon\api\client\contacts\Contact;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Newsletter\Model\Subscriber as NewsletterSubscriber;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class ContactBuilder
{
    public function __construct(
        private Config $config,
        private StoreManagerInterface $storeManager,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFactory $customerFactory,
        private SubscriberResource $subscriberResource
    ) {}

    public function buildCustomerContactObject(Customer $customer): Contact
    {
        $contact = new Contact();
        $contact->email = $customer->getEmail();
        $contact->standard_fields = $this->setCustomerStandardContactFields(
            $customer,
            $customer->getDefaultBillingAddress() ?: null
        );
        $contact->custom_fields = $this->setCustomerCustomContactFields(
            $customer,
            $customer->getDefaultBillingAddress() ?: null,
            $this->isCustomerSubscribed($customer),
            true
        );

        return $contact;
    }

    public function buildGuestCustomerContactObject(Order $order): Contact
    {
        $contact = new Contact();
        $contact->email = $order->getCustomerEmail();
        $contact->standard_fields = [
            'SALUTATION' => $order->getCustomerPrefix() ?? '',
            'FIRSTNAME' => $order->getCustomerFirstname(),
            'LASTNAME' => $order->getCustomerLastname(),
            'FULLNAME' => $order->getCustomerName(),
            'GENDER' => $this->getGenderShortCode($order->getCustomerGender()) ?? '',
            'LOCALE' => $this->config->getLocale($order->getStoreId()),
        ];
        $contact->custom_fields = [
            'magento_created' => true,
            'Magento_NL' => $this->isOrderCustomerSubscribed($order),
            'createdByTransaction' => true,
            'magento_storeview_id' => (string) $order->getStoreId(),
            'magento_domain' => $this->getBaseUrl($order->getStoreId()),
            'magento_source' => 'transaction',
        ];

        return $contact;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function buildNlSubscriberContactObject(NewsletterSubscriber $subscriber, array $customParams): Contact
    {
        $contact = new Contact();
        $contact->email = $subscriber->getEmail();

        $standardFields = [];

        if ($subscriber->getCustomerId()) {
            $customerData = $this->customerRepository->getById($subscriber->getCustomerId());
            $customer = $this->customerFactory->create();
            $customer->updateData($customerData);

            $standardFields = $this->setCustomerStandardContactFields(
                $customer,
                $customer->getDefaultBillingAddress() ?: null
            );
            $customFields = $this->setCustomerCustomContactFields(
                $customer,
                $customer->getDefaultBillingAddress() ?: null,
                $subscriber->isSubscribed(),
                false
            );
        } else {
            $standardFields['LOCALE'] = $this->config->getLocale($subscriber->getStoreId());
            $customFields = $this->getSubscriberCustomFields($subscriber);
        }

        $standardFields = $this->mergeFields($standardFields, $customParams['standard'] ?? []);
        $customFields = $this->mergeFields($customFields, $customParams['custom'] ?? []);

        $contact->standard_fields = $standardFields;
        $contact->custom_fields = $customFields;

        return $contact;
    }

    protected function setCustomerStandardContactFields(?Customer $customer, ?Address $address): array
    {
        $standardFields = [];

        if (! $customer) {
            return $standardFields;
        }

        $standardFields['SALUTATION'] = $customer->getPrefix() ?? '';
        $standardFields['FIRSTNAME'] = $customer->getFirstname();
        $standardFields['LASTNAME'] = $customer->getLastname();
        $standardFields['FULLNAME'] = $customer->getFirstname() . ' ' . $customer->getLastname();
        $standardFields['GENDER'] = $this->getGenderShortCode($customer->getGender()) ?? '';
        $standardFields['LOCALE'] = $this->config->getLocale($customer->getStoreId());

        if (! empty($address)) {
            $standardFields['ORGANIZATION'] = $address->getCompany() ?? '';
            $street = $address->getStreet();
            $standardFields['ADDRESS'] = is_array($street) ? ($street[0] ?? '') : ($street ?? '');
            $standardFields['CITY'] = $address->getCity() ?? '';
            $standardFields['ZIP'] = $address->getPostcode() ?? '';
            $standardFields['STATE'] = $address->getRegion() ?? '';
            $standardFields['COUNTRY'] = $address->getCountryId() ?? '';
        }

        return $standardFields;
    }

    protected function setCustomerCustomContactFields(
        ?Customer $customer,
        ?Address $address,
        bool $isSubscriber = false,
        bool $createForTransaction = false
    ): array {
        $customFields = [
            'magento_created' => true,
            'Magento_NL' => $isSubscriber,
            'createdByTransaction' => $createForTransaction,
            'magento_source' => $createForTransaction ? 'transaction' : 'newsletter',
        ];

        if (! $customer) {
            return $customFields;
        }

        if (! empty($address)) {
            $customFields['magento_phone'] = $address->getTelephone() ?? '';
        }
        $customFields['magento_storeview_id'] = (string) $customer->getStoreId();
        $customFields['magento_domain'] = $this->getBaseUrl($customer->getStoreId());

        return $customFields;
    }

    protected function getSubscriberCustomFields(NewsletterSubscriber $subscriber): array
    {
        return [
            'magento_created' => true,
            'Magento_NL' => $subscriber->isSubscribed(),
            'createdByTransaction' => false,
            'magento_storeview_id' => (string) $subscriber->getStoreId(),
            'magento_domain' => $this->getBaseUrl($subscriber->getStoreId()),
            'magento_source' => 'newsletter',
        ];
    }

    protected function getGenderShortCode(?int $genderId): ?string
    {
        return match ($genderId) {
            1 => 'm',
            2 => 'f',
            default => null,
        };
    }

    protected function getBaseUrl(?string $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId ?? null);
            return $store->getBaseUrl();
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    protected function mergeFields(array $base, array $override): array
    {
        return array_merge($base, $override);
    }

    public function isOrderCustomerSubscribed(Order $order): bool
    {
        try {
            if ($order->getCustomerIsGuest()) {
                return $this->isEmailSubscribed($order->getCustomerEmail(), $order->getStoreId());
            } else {
                return $this->isCustomerIdSubscribed($order->getCustomerId(), $order->getStoreId());
            }
        } catch (Throwable) {
            return false;
        }
    }

    public function isCustomerSubscribed(Customer $customer): bool
    {
        try {
            if ($this->isCustomerIdSubscribed($customer->getId(), $customer->getStoreId())) {
                return true;
            } else {
                return $this->isEmailSubscribed($customer->getEmail(), $customer->getStoreId());
            }
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public function isCustomerIdSubscribed(int $customerId, ?string $storeId): bool
    {
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $subscriberData = $this->subscriberResource->loadByCustomerId($customerId, $websiteId);

        return isset($subscriberData['subscriber_status']) && (int) $subscriberData['subscriber_status'] === 1;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function isEmailSubscribed(string $email, ?string $storeId): bool
    {
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $subscriberData = $this->subscriberResource->loadBySubscriberEmail($email, $websiteId);

        return isset($subscriberData['subscriber_status']) && (int) $subscriberData['subscriber_status'] === 1;
    }
}
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
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class ContactBuilder
{
    public function __construct(
        private Config $config,
        private StoreManagerInterface $storeManager,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFactory $customerFactory
    ) {}

    public function buildCustomerContactObject(Customer $customer, bool $isSubscriber = false): Contact
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
            $isSubscriber
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
            'Magento_NL' => false,
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
                true
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
        bool $isSubscriber = false
    ): array {
        $customFields = [
            'magento_created' => true,
            'Magento_NL' => $isSubscriber,
            'createdByTransaction' => !$isSubscriber,
            'magento_source' => $isSubscriber ? 'newsletter' : 'transaction',
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
            'Magento_NL' => true,
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
}
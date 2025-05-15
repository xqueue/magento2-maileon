<?php

namespace Xqueue\Maileon\Helper;

use Exception;
use Magento\Customer\Model\EmailNotification as MagentoCustomerEmailNotification;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Data\CustomerSecure;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Xqueue\Maileon\Model\Maileon\TransactionService;

class CustomerTransactionHelper
{
    public function __construct(
        private Config $config,
        private TransactionHelper $transactionHelper,
        private CustomerRegistry $customerRegistry,
        private StoreManagerInterface $storeManager,
        private CustomerViewHelper $customerViewHelper,
        private DataObjectProcessor $dataProcessor
    ) {}

    /**
     * @throws LocalizedException
     */
    public function emailAndPasswordChanged(Customer $customer, string $email): void
    {
        $this->sendCredentialChangeTX($customer, $email, ['email', 'password']);
    }

    /**
     * @throws LocalizedException
     */
    public function emailChanged(Customer $customer, string $email): void
    {
        $this->sendCredentialChangeTX($customer, $email, ['email']);
    }

    /**
     * @throws LocalizedException
     */
    public function passwordReset(Customer $customer): void
    {
        $this->sendCredentialChangeTX($customer, null, ['password']);
    }

    /**
     * @throws LocalizedException
     */
    public function passwordReminder(Customer $customer): void
    {
        $this->sendPasswordTX($customer, Config::PASSWORD_REMINDER_TX_NAME, true);
    }

    /**
     * @throws LocalizedException
     */
    public function passwordResetConfirmation(Customer $customer): void
    {
        $this->sendPasswordTX($customer, Config::PASSWORD_RESET_TX_NAME);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function newAccount(
        MagentoCustomerEmailNotification $subject,
        Customer $customer,
        string $type = 'customer/create_account/email_template',
        ?string $backUrl = null,
        ?int $sendemailStoreId = null
    ): void
    {
        $this->sendNewAccountTX($subject, $customer, $type, $backUrl, $sendemailStoreId);
    }

    /**
     * Send Maileon TX to customer about credential changes
     *
     * @throws LocalizedException
     * @throws Exception
     */
    protected function sendCredentialChangeTX(Customer $customer, ?string $newEmail, array $changedFields): void
    {
        $storeId = $customer->getStoreId() ?? $this->getWebsiteStoreId($customer);
        $customerEmailData = $this->getFullCustomerObject($customer);
        $store = $this->storeManager->getStore($storeId);
        $email = $newEmail ?? $customer->getEmail();

        if ($this->transactionHelper->updateOrCreateContactFromCustomer($customer, $newEmail)) {
            $transactionService = new TransactionService($this->config->getApiKey($storeId));

            $transactionService->sendTransaction(
                $email,
                Config::ACCOUNT_CREDENTIALS_CHANGED_TX_NAME,
                [
                    'fullname' => $customerEmailData->getData('name'),
                    'changed_field' => implode(', ', $changedFields),
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                    'store_email' => $this->config->getStoreEmail($storeId),
                    'store_phone' => $this->config->getStorePhone($storeId),
                ]
            );
        }
    }

    /**
     * Send password-related transactional email via Maileon.
     *
     * @throws LocalizedException
     * @throws Exception
     */
    protected function sendPasswordTX(
        Customer $customer,
        string $transactionType,
        bool $includeAccountUrl = false
    ): void {
        $storeId = $customer->getStoreId() ?? $this->getWebsiteStoreId($customer);
        $customerData = $this->getFullCustomerObject($customer);
        $store = $this->storeManager->getStore($storeId);

        $data = [
            'fullname' => $customerData->getData('name'),
            'psw_reset_url' => $this->buildPasswordResetUrl($store, $customer, $customerData),
            'store_id' => $storeId,
            'store_name' => $store->getName(),
        ];

        if ($includeAccountUrl) {
            $data['account_url'] = $this->buildAccountUrl($store);
        }

        if ($this->transactionHelper->updateOrCreateContactFromCustomer($customer)) {
            $transactionService = new TransactionService($this->config->getApiKey($storeId));

            $transactionService->sendTransaction(
                $customer->getEmail(),
                $transactionType,
                $data
            );
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws Exception
     */
    protected function sendNewAccountTX(
        MagentoCustomerEmailNotification $subject,
        Customer $customer,
        string $type = 'customer/create_account/email_template',
        ?string $backUrl = null,
        ?int $sendemailStoreId = null
    ): void {
        $types = $subject::TEMPLATE_TYPES;

        if (!isset($types[$type])) {
            throw new LocalizedException(
                __('The transactional account email type is incorrect. Verify and try again.')
            );
        }

        $storeId = $storeId ?? $this->getWebsiteStoreId($customer, $sendemailStoreId);
        $store = $this->storeManager->getStore($customer->getStoreId());
        $customerData = $this->getFullCustomerObject($customer);

        if ($this->transactionHelper->updateOrCreateContactFromCustomer($customer)) {
            $transactionService = new TransactionService($this->config->getApiKey($storeId));

            $transactionService->sendTransaction(
                $customer->getEmail(),
                Config::NEW_ACCOUNT_TX_NAME,
                [
                    'fullname' => $customerData->getData('name'),
                    'type' => $types[$type],
                    'account_url' => $this->buildAccountUrl($store),
                    'account_confirm_url' => $this->buildAccountConfirmUrl($store, $customer, $backUrl),
                    'psw_reset_url' => $this->buildPasswordResetUrl($store, $customer, $customerData),
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                ]
            );
        }
    }

    /**
     * Get either first store ID from a set website or the provided as default
     *
     * @throws LocalizedException
     */
    protected function getWebsiteStoreId(Customer $customer, ?int $defaultStoreId = null): int
    {
        if ($customer->getWebsiteId() != 0 && empty($defaultStoreId)) {
            $storeIds = $this->storeManager->getWebsite($customer->getWebsiteId())->getStoreIds();
            $defaultStoreId = reset($storeIds);
        }
        return $defaultStoreId;
    }

    /**
     * Create an object with data merged from Customer and CustomerSecure
     *
     * @throws NoSuchEntityException
     */
    protected function getFullCustomerObject(Customer $customer): CustomerSecure
    {
        // No need to flatten the custom attributes or nested objects since the only usage is for email templates and
        // object passed for events
        $mergedCustomerData = $this->customerRegistry->retrieveSecureData($customer->getId());
        $customerData = $this->dataProcessor
            ->buildOutputDataArray($customer->getDataModel(), CustomerInterface::class);
        $mergedCustomerData->addData($customerData);
        $mergedCustomerData->setData('name', $this->customerViewHelper->getCustomerName($customer->getDataModel()));
        return $mergedCustomerData;
    }

    protected function buildAccountUrl(StoreInterface $store): string
    {
        return $store->getBaseUrl() . 'customer/account/';
    }

    protected function buildPasswordResetUrl(StoreInterface $store, Customer $customer, $customerData): string
    {
        $params = http_build_query([
            'id' => $customer->getId(),
            'token' => $customerData->getRpToken()
        ]);
        return $store->getBaseUrl() . 'customer/account/createPassword/?' . $params;
    }

    protected function buildAccountConfirmUrl(StoreInterface $store, Customer $customer, ?string $backUrl = null): string
    {
        if (empty($backUrl)) {
            $backUrl = $store->getBaseUrl() . 'customer/account/index/?' . http_build_query([
                    'id' => $customer->getId(),
                    'key' => $customer->getConfirmation()
                ]);
        }

        return $store->getBaseUrl() . 'customer/account/confirm/?' . http_build_query(['back_url' => $backUrl]);
    }
}

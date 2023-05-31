<?php
declare(strict_types=1);

namespace Xqueue\Maileon\Model\Customer;

use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\EmailNotification as MagentoCustomerEmailNotification;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Data\CustomerSecure;
use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Exception\LocalizedException;
use Xqueue\Maileon\Model\Maileon\ContactCreate;
use Xqueue\Maileon\Model\Maileon\TransactionCreate;

class EmailNotification
{
    /**
     * Maileon plugin config
     *
     * @var array
     */
    protected $pluginConfig;

    /**
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CustomerViewHelper
     */
    protected $customerViewHelper;

    /**
     * @var DataObjectProcessor
     */
    protected $dataProcessor;

    public function __construct(
        CustomerRegistry $customerRegistry,
        StoreManagerInterface $storeManager,
        CustomerViewHelper $customerViewHelper,
        DataObjectProcessor $dataProcessor
    ) {
        $this->pluginConfig = $this->getPluginConfigValues();
        $this->customerRegistry = $customerRegistry;
        $this->storeManager = $storeManager;
        $this->customerViewHelper = $customerViewHelper;
        $this->dataProcessor = $dataProcessor;
    }

    /**
     * Override Customer credentials changed email notification
     *
     * @param MagentoCustomerEmailNotification $subject
     * @param callable $proceed
     * @param CustomerInterface $savedCustomer
     * @param string $origCustomerEmail
     * @param boolean $isPasswordChanged
     */
    public function aroundCredentialsChanged(
        MagentoCustomerEmailNotification $subject,
        callable $proceed,
        CustomerInterface $savedCustomer,
        $origCustomerEmail,
        $isPasswordChanged = false
    ) {
        if ($this->pluginConfig['credentialsChanged'] == 'yes') {
            if ($origCustomerEmail != $savedCustomer->getEmail()) {
                if ($isPasswordChanged) {
                    $this->emailAndPasswordChanged($savedCustomer, $origCustomerEmail);
                    $this->emailAndPasswordChanged($savedCustomer, $savedCustomer->getEmail());
                    return;
                }
    
                $this->emailChanged($savedCustomer, $origCustomerEmail);
                $this->emailChanged($savedCustomer, $savedCustomer->getEmail());
                return;
            }
    
            if ($isPasswordChanged) {
                $this->passwordReset($savedCustomer);
            }

            $result = null;
        } else {
            $result = $proceed(
                $savedCustomer,
                $origCustomerEmail,
                $isPasswordChanged
            );
        }

        return $result;
    }

    /**
     * Override send email with new customer password
     *
     * @param MagentoCustomerEmailNotification $subject
     * @param callable $proceed
     * @param CustomerInterface $customer
     */
    public function aroundPasswordReminder(
        MagentoCustomerEmailNotification $subject,
        callable $proceed,
        CustomerInterface $customer
    ) {
        if ($this->pluginConfig['passwordReminder'] == 'yes') {
            $storeId = $customer->getStoreId();
            if ($storeId === null) {
                $storeId = $this->getWebsiteStoreId($customer);
            }

            $customerEmailData = $this->getFullCustomerObject($customer);
            $store = $this->storeManager->getStore($storeId);

            $baseUrl = $store->getBaseUrl();
            $params = '?id=' . $customer->getId() . '&token=' . $customerEmailData->getRpToken();
            $pswResetUrl = $baseUrl . 'customer/account/createPassword/' . $params;
            $accountUrl = $baseUrl . 'customer/account/';

            $contactCreated = $this->updateOrCreateContact(
                $customer,
                $customerEmailData,
                $customer->getEmail(),
                (string) $storeId
            );
    
            if ($contactCreated) {
                $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');
    
                $transactionCreate->sendTransaction(
                    $customer->getEmail(),
                    'magento_password_reminder_v1',
                    [
                        'fullname' => $customerEmailData->getData('name'),
                        'account_url' => $accountUrl,
                        'psw_reset_url' => $pswResetUrl,
                        'store_id' => $storeId,
                        'store_name' => $store->getName()
                    ]
                );
            }

            $result = null;
        } else {
            $result = $proceed($customer);
        }

        return $result;
    }

    /**
     * Override send email with reset password confirmation link
     *
     * @param MagentoCustomerEmailNotification $subject
     * @param callable $proceed
     * @param CustomerInterface $customer
     */
    public function aroundPasswordResetConfirmation(
        MagentoCustomerEmailNotification $subject,
        callable $proceed,
        CustomerInterface $customer
    ) {
        if ($this->pluginConfig['passwordResetConfirm'] == 'yes') {
            $storeId = $customer->getStoreId();
            if ($storeId === null) {
                $storeId = $this->getWebsiteStoreId($customer);
            }

            $customerEmailData = $this->getFullCustomerObject($customer);
            $store = $this->storeManager->getStore($storeId);

            $baseUrl = $store->getBaseUrl();
            $params = '?id=' . $customer->getId() . '&token=' . $customerEmailData->getRpToken();
            $pswResetUrl = $baseUrl . 'customer/account/createPassword/' . $params;

            $contactCreated = $this->updateOrCreateContact(
                $customer,
                $customerEmailData,
                $customer->getEmail(),
                (string) $storeId
            );
    
            if ($contactCreated) {
                $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');
    
                $transactionCreate->sendTransaction(
                    $customer->getEmail(),
                    'magento_password_reset_confirmation_v1',
                    [
                        'fullname' => $customerEmailData->getData('name'),
                        'psw_reset_url' => $pswResetUrl,
                        'store_id' => $storeId,
                        'store_name' => $store->getName()
                    ]
                );
            }

            $result = null;
        } else {
            $result = $proceed($customer);
        }

        return $result;
    }

    /**
     * Override send email with new account related information
     *
     * @param MagentoCustomerEmailNotification $subject
     * @param callable $proceed
     * @param CustomerInterface $customer
     * @param string $type
     * @param string $backUrl
     * @param int|null $storeId
     * @param string $sendemailStoreId
     * @throws LocalizedException
     */
    public function aroundNewAccount(
        MagentoCustomerEmailNotification $subject,
        callable $proceed,
        CustomerInterface $customer,
        $type = 'customer/create_account/email_template',
        $backUrl = '',
        $storeId = null,
        $sendemailStoreId = null
    ) {
        if ($this->pluginConfig['newAccount'] == 'yes') {
            $types = $subject::TEMPLATE_TYPES;

            if (!isset($types[$type])) {
                throw new LocalizedException(
                    __('The transactional account email type is incorrect. Verify and try again.')
                );
            }

            if ($storeId === null) {
                $storeId = $this->getWebsiteStoreId($customer, $sendemailStoreId);
            }

            $store = $this->storeManager->getStore($customer->getStoreId());
            $customerEmailData = $this->getFullCustomerObject($customer);

            $baseUrl = $store->getBaseUrl();
            $params = '?id=' . $customer->getId() . '&token=' . $customerEmailData->getRpToken();
            $pswResetUrl = $baseUrl . 'customer/account/createPassword/' . $params;
            $accountUrl = $baseUrl . 'customer/account/';
            $accountConfirmUrl = '';

            if (!empty($backUrl)) {
                $acuParams = '?id=' . $customer->getId() . '&key=' . $customer->getConfirmation() . '&back_url=' . $backUrl;
                $accountConfirmUrl = $baseUrl . 'customer/account/confirm/' . $acuParams;
            }

            $contactCreated = $this->updateOrCreateContact(
                $customer,
                $customerEmailData,
                $customer->getEmail(),
                (string) $storeId
            );
    
            if ($contactCreated) {
                $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');
    
                $transactionCreate->sendTransaction(
                    $customer->getEmail(),
                    'magento_new_account_v1',
                    [
                        'fullname' => $customerEmailData->getData('name'),
                        'type' => $types[$type],
                        'account_url' => $accountUrl,
                        'account_confirm_url' => $accountConfirmUrl,
                        'psw_reset_url' => $pswResetUrl,
                        'store_id' => $storeId,
                        'store_name' => $store->getName()
                    ]
                );
            }

            $result = null;
        } else {
            $result = $proceed($customer);
        }

        return $result;
    }

    /**
     * Send email to customer when his email and password is changed
     *
     * @param CustomerInterface $customer
     * @param string $email
     * @return void
     */
    private function emailAndPasswordChanged(CustomerInterface $customer, $email): void
    {
        $storeId = $customer->getStoreId();
        if ($storeId === null) {
            $storeId = $this->getWebsiteStoreId($customer);
        }

        $customerEmailData = $this->getFullCustomerObject($customer);
        $store = $this->storeManager->getStore($storeId);

        $contactCreated = $this->updateOrCreateContact(
            $customer,
            $customerEmailData,
            $email,
            (string) $storeId
        );

        if ($contactCreated) {
            $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');

            $transactionCreate->sendTransaction(
                $email,
                'magento_account_credentials_changed_v1',
                [
                    'fullname' => $customerEmailData->getData('name'),
                    'changed_field' => 'email, password',
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                    'store_email' => $this->pluginConfig['storeEmail'],
                    'store_phone' => $this->pluginConfig['storePhone']
                ]
            );
        }
    }

    /**
     * Send email to customer when his email is changed
     *
     * @param CustomerInterface $customer
     * @param string $email
     * @return void
     */
    private function emailChanged(CustomerInterface $customer, $email): void
    {
        $storeId = $customer->getStoreId();
        if ($storeId === null) {
            $storeId = $this->getWebsiteStoreId($customer);
        }

        $customerEmailData = $this->getFullCustomerObject($customer);
        $store = $this->storeManager->getStore($storeId);

        $contactCreated = $this->updateOrCreateContact(
            $customer,
            $customerEmailData,
            $email,
            (string) $storeId
        );

        if ($contactCreated) {
            $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');

            $transactionCreate->sendTransaction(
                $email,
                'magento_account_credentials_changed_v1',
                [
                    'fullname' => $customerEmailData->getData('name'),
                    'changed_field' => 'email',
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                    'store_email' => $this->pluginConfig['storeEmail'],
                    'store_phone' => $this->pluginConfig['storePhone']
                ]
            );
        }
    }

    /**
     * Send email to customer when his password is reset
     *
     * @param CustomerInterface $customer
     * @return void
     */
    private function passwordReset(CustomerInterface $customer): void
    {
        $storeId = $customer->getStoreId();
        if ($storeId === null) {
            $storeId = $this->getWebsiteStoreId($customer);
        }

        $customerEmailData = $this->getFullCustomerObject($customer);
        $store = $this->storeManager->getStore($storeId);

        $contactCreated = $this->updateOrCreateContact(
            $customer,
            $customerEmailData,
            $customer->getEmail(),
            (string) $storeId
        );

        if ($contactCreated) {
            $transactionCreate = new TransactionCreate($this->pluginConfig['maileonApiKey'], 'no');

            $transactionCreate->sendTransaction(
                $customer->getEmail(),
                'magento_account_credentials_changed_v1',
                [
                    'fullname' => $customerEmailData->getData('name'),
                    'changed_field' => 'password',
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                    'store_email' => $this->pluginConfig['storeEmail'],
                    'store_phone' => $this->pluginConfig['storePhone']
                ]
            );
        }
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

        $config['credentialsChanged'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/customer_related_transactions/credentials_changed', ScopeInterface::SCOPE_STORE);

        $config['passwordReminder'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/customer_related_transactions/password_reminder', ScopeInterface::SCOPE_STORE);

        $config['passwordResetConfirm'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/customer_related_transactions/password_reset_confirm', ScopeInterface::SCOPE_STORE);

        $config['newAccount'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('syncplugin/customer_related_transactions/new_account', ScopeInterface::SCOPE_STORE);

        $config['storeEmail'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('trans_email/ident_support/email', ScopeInterface::SCOPE_STORE);

        $config['storePhone'] = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('general/store_information/phone', ScopeInterface::SCOPE_STORE);

        return $config;
    }

    /**
     * Get either first store ID from a set website or the provided as default
     *
     * @param CustomerInterface $customer
     * @param int|string|null $defaultStoreId
     * @return int
     */
    private function getWebsiteStoreId($customer, $defaultStoreId = null): int
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
     * @param CustomerInterface $customer
     * @return CustomerSecure
     */
    private function getFullCustomerObject($customer): CustomerSecure
    {
        // No need to flatten the custom attributes or nested objects since the only usage is for email templates and
        // object passed for events
        $mergedCustomerData = $this->customerRegistry->retrieveSecureData($customer->getId());
        $customerData = $this->dataProcessor
            ->buildOutputDataArray($customer, CustomerInterface::class);
        $mergedCustomerData->addData($customerData);
        $mergedCustomerData->setData('name', $this->customerViewHelper->getCustomerName($customer));
        return $mergedCustomerData;
    }

    /**
     * Update or create the contact at Maileon
     *
     * @param CustomerInterface $customer
     * @param CustomerSecure $customerEmailData
     * @param string $email
     * @param string $storeId
     * @return boolean
     */
    private function updateOrCreateContact(
        CustomerInterface $customer,
        CustomerSecure $customerEmailData,
        string $email,
        string $storeId
    ): bool {
        $contact_create = new ContactCreate(
            $this->pluginConfig['maileonApiKey'],
            $email,
            'none',
            false,
            false,
            null,
            false
        );

        $standard_fields = array(
            'FIRSTNAME' => $customer->getFirstname(),
            'LASTNAME' => $customer->getLastname(),
            'FULLNAME' => $customerEmailData->getData('name')
        );

        $custom_fields = array(
            'magento_storeview_id' => $storeId,
            'magento_source' => 'transaction_create'
        );

        return $contact_create->makeMalieonContact(array(), $standard_fields, $custom_fields);
    }
}

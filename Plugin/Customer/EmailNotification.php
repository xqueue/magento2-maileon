<?php

namespace Xqueue\Maileon\Plugin\Customer;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\EmailNotification as MagentoCustomerEmailNotification;
use Magento\Customer\Api\Data\CustomerInterface;
use Throwable;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Helper\CustomerTransactionHelper;
use Xqueue\Maileon\Logger\Logger;
use Xqueue\Maileon\Service\EmailContext;

class EmailNotification
{
    public function __construct(
        private EmailContext $emailContext,
        private Config $config,
        private CustomerTransactionHelper $customerTransactionHelper,
        private CustomerFactory $customerFactory,
        private Logger $logger
    ) {}

    public function beforeCredentialsChanged(): void
    {
        $this->emailContext->setEmailType('credentials_changed');
    }

    public function afterCredentialsChanged(
        MagentoCustomerEmailNotification $subject,
        $result,
        CustomerInterface $savedCustomer,
        $origCustomerEmail,
        $isPasswordChanged
    ): void {
        $this->emailContext->clear();

        try {
            if ($this->config->isCredentialsChangedTXEnabled()) {
                $customer = $this->customerFactory->create();
                $customer->updateData($savedCustomer);
                if ($origCustomerEmail != $savedCustomer->getEmail()) {
                    if ($isPasswordChanged) {
                        $this->customerTransactionHelper->emailAndPasswordChanged($customer, $origCustomerEmail);
                        $this->customerTransactionHelper->emailAndPasswordChanged($customer, $savedCustomer->getEmail());
                        return;
                    }

                    $this->customerTransactionHelper->emailChanged($customer, $origCustomerEmail);
                    $this->customerTransactionHelper->emailChanged($customer, $savedCustomer->getEmail());
                    return;
                }

                if ($isPasswordChanged) {
                    $this->customerTransactionHelper->passwordReset($customer);
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    public function beforePasswordReminder(): void
    {
        $this->emailContext->setEmailType('psw_reminder');
    }

    public function afterPasswordReminder(
        MagentoCustomerEmailNotification $subject,
        $result,
        CustomerInterface $customer,
    ): void {
        $this->emailContext->clear();

        try {
            if ($this->config->isPswReminderTXEnabled()) {
                $customerModel = $this->customerFactory->create();
                $customerModel->updateData($customer);
                $this->customerTransactionHelper->passwordReminder($customerModel);
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    public function beforePasswordResetConfirmation(): void
    {
        $this->emailContext->setEmailType('psw_reset');
    }

    public function afterPasswordResetConfirmation(
        MagentoCustomerEmailNotification $subject,
        $result,
        CustomerInterface $customer,
    ): void {
        $this->emailContext->clear();

        try {
            if ($this->config->isPswResetTXEnabled()) {
                $customerModel = $this->customerFactory->create();
                $customerModel->updateData($customer);
                $this->customerTransactionHelper->passwordResetConfirmation($customerModel);
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    public function beforeNewAccount(): void
    {
        $this->emailContext->setEmailType('new_account');
    }

    public function afterNewAccount(
        MagentoCustomerEmailNotification $subject,
        $result,
        CustomerInterface $customer,
        $type = 'customer/create_account/email_template',
        $backUrl = '',
        $storeId = null,
        $sendemailStoreId = null
    ): void {
        $this->emailContext->clear();

        try {
            if ($this->config->isNewAccountTXEnabled()) {
                $customerModel = $this->customerFactory->create();
                $customerModel->updateData($customer);
                $this->customerTransactionHelper->newAccount($subject, $customerModel, $type, $backUrl, $sendemailStoreId);
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}

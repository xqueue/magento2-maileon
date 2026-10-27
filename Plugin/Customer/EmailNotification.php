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

    public function aroundCredentialsChanged(MagentoCustomerEmailNotification $subject, callable $proceed, ...$args): mixed
    {
        $result = $this->proceedWithEmailType('credentials_changed', $proceed, $args);
        $this->sendCredentialsChangedTransaction($subject, ...$args);

        return $result;
    }

    private function sendCredentialsChangedTransaction(
        MagentoCustomerEmailNotification $subject,
        CustomerInterface $savedCustomer,
        $origCustomerEmail,
        $isPasswordChanged = false
    ): void {
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

    public function aroundPasswordReminder(MagentoCustomerEmailNotification $subject, callable $proceed, ...$args): mixed
    {
        $result = $this->proceedWithEmailType('psw_reminder', $proceed, $args);
        $this->sendPasswordReminderTransaction($subject, ...$args);

        return $result;
    }

    private function sendPasswordReminderTransaction(
        MagentoCustomerEmailNotification $subject,
        CustomerInterface $customer,
    ): void {
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

    public function aroundPasswordResetConfirmation(MagentoCustomerEmailNotification $subject, callable $proceed, ...$args): mixed
    {
        $result = $this->proceedWithEmailType('psw_reset', $proceed, $args);
        $this->sendPasswordResetConfirmationTransaction($subject, ...$args);

        return $result;
    }

    private function sendPasswordResetConfirmationTransaction(
        MagentoCustomerEmailNotification $subject,
        CustomerInterface $customer,
    ): void {
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

    public function aroundNewAccount(MagentoCustomerEmailNotification $subject, callable $proceed, ...$args): mixed
    {
        $result = $this->proceedWithEmailType('new_account', $proceed, $args);
        $this->sendNewAccountTransaction($subject, ...$args);

        return $result;
    }

    private function sendNewAccountTransaction(
        MagentoCustomerEmailNotification $subject,
        CustomerInterface $customer,
        $type = 'customer/create_account/email_template',
        $backUrl = '',
        $storeId = null,
        $sendemailStoreId = null
    ): void {
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

    /**
     * Runs the core method with the e-mail type set for OverrideRecipient, clearing it even on exceptions.
     */
    private function proceedWithEmailType(string $emailType, callable $proceed, array $args): mixed
    {
        $this->emailContext->setEmailType($emailType);

        try {
            return $proceed(...$args);
        } finally {
            $this->emailContext->clear();
        }
    }
}

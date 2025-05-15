<?php

namespace Xqueue\Maileon\Plugin\Newsletter;

use Xqueue\Maileon\Service\EmailContext;

class Subscriber
{
    public function __construct(
        private EmailContext $emailContext
    ) {}

    public function beforeSendConfirmationRequestEmail(): void
    {
        $this->emailContext->setEmailType('nl_confirm');
    }

    public function afterSendConfirmationRequestEmail(): void
    {
        $this->emailContext->clear();
    }

    public function beforeSendConfirmationSuccessEmail(): void
    {
        $this->emailContext->setEmailType('nl_success');
    }

    public function afterSendConfirmationSuccessEmail(): void
    {
        $this->emailContext->clear();
    }

    public function beforeSendUnsubscriptionEmail(): void
    {
        $this->emailContext->setEmailType('nl_unsubscribe');
    }

    public function afterSendUnsubscriptionEmail(): void
    {
        $this->emailContext->clear();
    }
}

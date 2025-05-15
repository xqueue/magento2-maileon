<?php

namespace Xqueue\Maileon\Plugin\Order\Email;

use Xqueue\Maileon\Service\EmailContext;

class CreditmemoCommentSender
{
    public function __construct(
        private EmailContext $emailContext
    ) {}

    public function beforeSend(): void
    {
        $this->emailContext->setEmailType('credit_memo');
    }

    public function afterSend(): void
    {
        $this->emailContext->clear();
    }
}

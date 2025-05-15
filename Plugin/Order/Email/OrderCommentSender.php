<?php

namespace Xqueue\Maileon\Plugin\Order\Email;

use Xqueue\Maileon\Service\EmailContext;

class OrderCommentSender
{
    public function __construct(
        private EmailContext $emailContext
    ) {}

    public function beforeSend(): void
    {
        $this->emailContext->setEmailType('order_status');
    }

    public function afterSend(): void
    {
        $this->emailContext->clear();
    }
}

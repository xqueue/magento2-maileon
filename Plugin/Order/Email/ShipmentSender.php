<?php

namespace Xqueue\Maileon\Plugin\Order\Email;

use Xqueue\Maileon\Service\EmailContext;

class ShipmentSender
{
    public function __construct(
        private EmailContext $emailContext
    ) {}

    public function beforeSend(): void
    {
        $this->emailContext->setEmailType('shipment');
    }

    public function afterSend(): void
    {
        $this->emailContext->clear();
    }
}

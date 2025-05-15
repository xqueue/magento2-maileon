<?php

namespace Xqueue\Maileon\Cron;

use Xqueue\Maileon\Service\SendAbandonedCartsProcessor;

class SendAbandonedCartsEmails
{
    public function __construct(
        private SendAbandonedCartsProcessor $processor
    ) {}

    public function execute(): void
    {
        $this->processor->execute();
    }
}

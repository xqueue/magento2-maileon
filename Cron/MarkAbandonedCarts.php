<?php

namespace Xqueue\Maileon\Cron;

use Xqueue\Maileon\Service\MarkAbandonedCartsProcessor;

class MarkAbandonedCarts
{
    public function __construct(
        private MarkAbandonedCartsProcessor $processor
    ) {}

    public function execute(): void
    {
        $this->processor->execute();
    }
}

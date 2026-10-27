<?php

namespace Xqueue\Maileon\Plugin\Order\Email;

use Xqueue\Maileon\Service\EmailContext;

class ShipmentSender
{
    public function __construct(
        private EmailContext $emailContext
    ) {}

    /**
     * Marks the e-mail type for OverrideRecipient while the core sender runs.
     * Returns the sender's own result (core async sending and payment modules rely on it) and
     * clears the context even if sending throws.
     */
    public function aroundSend($subject, callable $proceed, ...$args): mixed
    {
        $this->emailContext->setEmailType('shipment');

        try {
            return $proceed(...$args);
        } finally {
            $this->emailContext->clear();
        }
    }
}

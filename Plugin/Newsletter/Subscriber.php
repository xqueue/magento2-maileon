<?php

namespace Xqueue\Maileon\Plugin\Newsletter;

use Xqueue\Maileon\Service\EmailContext;

/**
 * Marks the newsletter e-mail type for OverrideRecipient while the core method runs.
 * Each around plugin returns the core result (the subscriber, for chaining) and clears
 * the context even if sending throws.
 */
class Subscriber
{
    public function __construct(
        private EmailContext $emailContext
    ) {}

    public function aroundSendConfirmationRequestEmail($subject, callable $proceed): mixed
    {
        return $this->runWithEmailType('nl_confirm', $proceed);
    }

    public function aroundSendConfirmationSuccessEmail($subject, callable $proceed): mixed
    {
        return $this->runWithEmailType('nl_success', $proceed);
    }

    public function aroundSendUnsubscriptionEmail($subject, callable $proceed): mixed
    {
        return $this->runWithEmailType('nl_unsubscribe', $proceed);
    }

    private function runWithEmailType(string $emailType, callable $proceed): mixed
    {
        $this->emailContext->setEmailType($emailType);

        try {
            return $proceed();
        } finally {
            $this->emailContext->clear();
        }
    }
}

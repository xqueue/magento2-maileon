<?php

namespace Xqueue\Maileon\Plugin;

use Magento\Framework\Mail\Template\TransportBuilder;
use Xqueue\Maileon\Helper\Config;
use Xqueue\Maileon\Service\EmailContext;

class OverrideRecipient
{
    public function __construct(
        private Config $config,
        private EmailContext $emailContext
    ) {}

    public function beforeAddTo(TransportBuilder $subject, $email, $name = null)
    {
        $emailType = $this->emailContext->getEmailType();
        $overrideEmail = 'do_not_send@example.invalid';

        if ($emailType && $this->config->isOverrideEnabled($emailType)) {
            return [$overrideEmail, ucfirst($emailType) . ' Email Blocked'];
        }

        return [$email, $name];
    }
}
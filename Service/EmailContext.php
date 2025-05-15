<?php

namespace Xqueue\Maileon\Service;

class EmailContext
{
    private ?string $emailType = null;

    public function setEmailType(string $type): void
    {
        $this->emailType = $type;
    }

    public function getEmailType(): ?string
    {
        return $this->emailType;
    }

    public function clear(): void
    {
        $this->emailType = null;
    }
}

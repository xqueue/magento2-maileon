<?php

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\contacts\Contacts;
use de\xqueue\maileon\api\client\contacts\ContactsService;
use de\xqueue\maileon\api\client\contacts\Permission;
use de\xqueue\maileon\api\client\contacts\SynchronizationMode;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use Psr\Log\LoggerInterface;
use Xqueue\Maileon\Helper\Config;

class ImportService
{
    private ContactsService $contactsService;

    private TransactionsService $transactionsService;

    public function __construct(
        protected LoggerInterface $logger,
        protected Config $config
    ) {
        $maileonConfig = [
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->config->getApiKey(),
            'TIMEOUT' => 30,
        ];

        $this->contactsService = new ContactsService($maileonConfig);
        $this->transactionsService = new TransactionsService($maileonConfig);
    }

    public function syncContacts(Contacts $contacts, bool $withoutPermission = false): bool
    {
        if ($withoutPermission) {
            $permission = Permission::$NONE;
        } else {
            $permission = Permission::getPermission($this->config->getNewsletterSubscriberImportPermission());
        }

        $response = $this->contactsService->synchronizeContacts(
            $contacts,
            $permission,
            SynchronizationMode::$UPDATE,
            false,
            true,
            true,
            false
        );

        return $response->isSuccess();
    }

    public function sendTransactions(array $transactions): bool
    {
        $response = $this->transactionsService->createTransactions($transactions, true, true);

        return $response->isSuccess();
    }
}

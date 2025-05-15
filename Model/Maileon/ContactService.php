<?php

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\contacts\ContactsService;
use de\xqueue\maileon\api\client\contacts\Contact;
use de\xqueue\maileon\api\client\contacts\SynchronizationMode;
use de\xqueue\maileon\api\client\reports\ReportsService;
use Xqueue\Maileon\Helper\Config;

class ContactService
{
    private ContactsService $contactsService;
    private ReportsService $reportsService;

    public function __construct(
        private string $apiKey,
        private string $email,
        private bool $doiProcess,
        private bool $doiPlusProcess,
        private ?string $doiKey = null
    ) {
        $maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apiKey,
            'TIMEOUT' => 20
        );

        $this->contactsService = new ContactsService($maileon_config);
        $this->reportsService = new ReportsService($maileon_config);
    }

    public function createOrUpdateMaileonContact(
        Contact $contact
    ): bool {
        $this->checkCustomFields($contact->custom_fields);

        $response = $this->contactsService->createContact(
            $contact,
            SynchronizationMode::$UPDATE,
            'Magento2',
            'Magento2 Plugin',
            $this->doiProcess,
            $this->doiPlusProcess,
            $this->doiKey ?? ''
        );

        return $response->isSuccess();
    }

    public function getPermission(bool $buyerEnabled, string $buyerPermission): string
    {
        $response = $this->reportsService->getUnsubscribers(
            contactEmails: [$this->email]
        );

        $unsubscribers = $response->getResult();
        $unsubscribed = !empty($unsubscribers);

        return ($buyerEnabled && !$unsubscribed)
            ? $buyerPermission
            : 'none';
    }

    public function checkCustomFields(array $customFields): bool
    {
        $customFieldsResponse = $this->contactsService->getCustomFields();
        $customFieldsFromMaileon = $customFieldsResponse->getResult();

        foreach (Config::CUSTOM_FIELDS as $fieldName => $fieldType) {
            if (!array_key_exists($fieldName, $customFieldsFromMaileon->custom_fields)) {
                $this->contactsService->createCustomField($fieldName, $fieldType);
            }
        }

        if (!empty($customFields)) {
            $remainingFields = array_diff_key($customFields, Config::CUSTOM_FIELDS);

            foreach ($remainingFields as $fieldName => $fieldValue) {
                if (!array_key_exists($fieldName, $customFieldsFromMaileon->custom_fields)) {
                    $this->contactsService->createCustomField($fieldName);
                }
            }
        }

        return true;
    }

    public function maileonContactIsExists(): bool
    {
        $response = $this->contactsService->getContactByEmail($this->email);

        return $response->isSuccess();
    }

    public function unsubscribeMaileonContact(): bool
    {
        $response = $this->contactsService->unsubscribeContactByEmail($this->email);

        return $response->isSuccess();
    }
}

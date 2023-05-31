<?php

/**
 * Create/unsubscribe Maileon Contact
 */

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\contacts\ContactsService;
use de\xqueue\maileon\api\client\contacts\Contact;
use de\xqueue\maileon\api\client\contacts\Permission;
use de\xqueue\maileon\api\client\contacts\StandardContactField;
use de\xqueue\maileon\api\client\contacts\SynchronizationMode;
use de\xqueue\maileon\api\client\reports\ReportsService;
use de\xqueue\maileon\api\client\MaileonAPIException;
use Psr\Log\LoggerInterface;

class ContactCreate
{
    /**
     * Maileon API key
     *
     * @var string
     */
    private $apikey;

    /**
     * Email address
     *
     * @var string
     */
    private $email;

    /**
     * Maileon permission
     *
     * @var string
     */
    private $permission;

    /**
     * Maileon DOI process
     *
     * @var boolean
     */
    private $doiprocess;

    /**
     * Maileon DOI+ process
     *
     * @var boolean
     */
    private $doiplusprocess;

    /**
     * Maileon DOI key
     *
     * @var string
     */
    private $doikey;

    /**
     * Print CURL debug data
     *
     * @var boolean
     */
    private $print_curl;

    /**
     * Maileon config
     *
     * @var array
     */
    private $maileon_config;

    /**
     * Logger interface
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string $apikey
     * @param string $email
     * @param string $permission
     * @param string|boolean $doiprocess
     * @param string|boolean $doiplusprocess
     * @param string $doikey
     * @param string|boolean $print_curl
     */
    public function __construct($apikey, $email, $permission, $doiprocess, $doiplusprocess, $doikey, $print_curl)
    {
        $this->apikey         = $apikey;
        $this->email          = $email;
        $this->permission     = $permission;
        $this->doiprocess     = filter_var($doiprocess, FILTER_VALIDATE_BOOLEAN);
        $this->doiplusprocess = filter_var($doiplusprocess, FILTER_VALIDATE_BOOLEAN);
        $this->doikey         = $doikey;
        $this->print_curl     = filter_var($print_curl, FILTER_VALIDATE_BOOLEAN);

        $this->maileon_config = array(
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->apikey,
            'TIMEOUT' => 35
        );

        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
    }

    /**
     * Set Permission
     *
     * @param string $permission
     * @return void
     */
    public function setPermission($permission)
    {
        $this->permission = $permission;
    }

    /**
     * Create Maileon Contact
     *
     * @param array $subscriber_data
     * @param array $standard_fields
     * @param array $custom_fields
     *
     * @return boolean
     */
    public function makeMalieonContact($subscriber_data = array(), $standard_fields = array(), $custom_fields = array())
    {
        $doiprocess = false;
        $doiplusprocess = false;

        if ($this->doiprocess) {
            $doiprocess = true;
        }

        if ($this->doiplusprocess) {
            $doiprocess = true;
            $doiplusprocess = true;
        }

        $contacts_service = new ContactsService($this->maileon_config);
        $contacts_service->setDebug($this->print_curl);

        $this->checkCustomFields($custom_fields);

        $contact = $this->prepareContact($subscriber_data, $standard_fields, $custom_fields);

        switch ($this->permission) {
            case 'none':
                $contact->permission = Permission::$NONE;
                break;

            case 'single_optin':
                $contact->permission = Permission::$SOI;
                break;

            case 'confirmed_optin':
                $contact->permission = Permission::$COI;
                break;

            case 'double_optin':
                if ($doiprocess) {
                    $contact->permission = Permission::$NONE;
                } else {
                    $contact->permission = Permission::$DOI;
                }
                break;

            case 'double_optin_plus':
                if ($doiplusprocess) {
                    $contact->permission = Permission::$NONE;
                } else {
                    $contact->permission = Permission::$DOI_PLUS;
                }
                break;
        }

        try {
            if ($doiprocess || $doiplusprocess) {
                $maileon_response = $contacts_service->createContact(
                    $contact,
                    SynchronizationMode::$UPDATE,
                    '',
                    '',
                    $doiprocess,
                    $doiplusprocess,
                    $this->doikey
                );
            } else {
                $maileon_response = $contacts_service->createContact(
                    $contact,
                    SynchronizationMode::$UPDATE
                );
            }
        } catch (MaileonAPIException $e) {
            $this->logger->error('Error at create Contact. Message: ' . (string) $e->getMessage());
        }

        return $maileon_response->isSuccess();
    }

    /**
     * Get permission
     *
     * @param boolean $buyer_enabled
     * @param string $buyer_permission
     * @return string
     */
    public function getPermission($buyer_enabled, $buyer_permission)
    {
        $reportsService = new ReportsService($this->maileon_config);

        try {
            $response = $reportsService->getUnsubscribers(null, null, null, null, array($this->email));
            $unsubscribers = $response->getResult();
        } catch (MaileonAPIException $e) {
            $this->logger->error(
                (string) $e->getMessage()
            );
        }

        if (empty($unsubscribers)) {
            $unsubscribed = false;
        } else {
            $unsubscribed = true;
        }

        if ($buyer_enabled) {
            if ($unsubscribed) {
                $permission = 'none';
            } else {
                $permission = $buyer_permission;
            }
        } else {
            $permission = 'none';
        }

        return $permission;
    }

    /**
     * Create custom fields at Maileon if not exist
     *
     * @param array $custom_fields
     * @return void
     */
    public function checkCustomFields($custom_fields)
    {
        $contacts_service = new ContactsService($this->maileon_config);
        $contacts_service->setDebug($this->print_curl);

        try {
            $customfields = $contacts_service->getCustomFields();
            $cf_result = $customfields->getResult();
        } catch (MaileonAPIException $e) {
            $this->logger->error('Error at get CustomFields. Message: ' . (string) $e->getMessage());
        }

        if (!array_key_exists('Magento_NL', $cf_result->custom_fields)) {
            $contacts_service->createCustomField('Magento_NL', 'boolean');
        }

        if (!empty($custom_fields)) {
            foreach ($custom_fields as $field_name => $field_value) {
                if (!array_key_exists($field_name, $cf_result->custom_fields)) {
                    $contacts_service->createCustomField($field_name, 'string');
                }
            }
        }
    }

    /**
     * Create Contact obj
     *
     * @param array $subscriber_data
     * @param array $standard_fields
     * @param array $custom_fields
     * @return Contact
     */
    public function prepareContact($subscriber_data = array(), $standard_fields = array(), $custom_fields = array())
    {
        $contact = new Contact();
        $contact->email = $this->email;
        $contact->standard_fields = $standard_fields;
        $contact->custom_fields = $custom_fields;

        if (!empty($subscriber_data)) {
            if (array_key_exists('firstname', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$FIRSTNAME] = $subscriber_data['firstname'];
            }
            if (array_key_exists('lastname', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$LASTNAME] = $subscriber_data['lastname'];
            }
            if (array_key_exists('fullname', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$FULLNAME] = $subscriber_data['fullname'];
            }
            if (array_key_exists('salutation', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$SALUTATION] = $subscriber_data['salutation'];
            }
            if (array_key_exists('street', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$ADDRESS] = $subscriber_data['street'];
            }
            if (array_key_exists('zipcode', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$ZIP] = $subscriber_data['zipcode'];
            }
            if (array_key_exists('city', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$CITY] = $subscriber_data['city'];
            }
            if (array_key_exists('company', $subscriber_data)) {
                $contact->standard_fields[StandardContactField::$ORGANIZATION] = $subscriber_data['company'];
            }
        }

        $contact->custom_fields['Magento_NL'] = true;

        return $contact;
    }

    /**
     * Check Contact is exist at Maileon
     *
     * @return boolean
     */

    public function maileonContactIsExists()
    {
        $contactsService = new ContactsService($this->maileon_config);
        $contactsService->setDebug($this->print_curl);

        try {
            $response = $contactsService->getContactByEmail($this->email);
        } catch (MaileonAPIException $e) {
            $this->logger->error(
                (string) $e->getMessage()
            );
        }

        return $response->isSuccess();
    }

    /**
     * Unsubscribe Maileon Contact
     *
     * @return boolean
     */
    public function unsubscribeMalieonContact()
    {
        $contactsService = new ContactsService($this->maileon_config);
        $contactsService->setDebug($this->print_curl);

        try {
            $response = $contactsService->unsubscribeContactByEmail($this->email);
        } catch (MaileonAPIException $e) {
            $this->logger->error(
                (string) $e->getMessage()
            );
        }

        return $response->isSuccess();
    }
}

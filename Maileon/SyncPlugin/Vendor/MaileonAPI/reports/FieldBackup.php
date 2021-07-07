<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\reports;

/**
 * Field Backups are the values of contact fields that have been backed up for mailings because of a backup instruction. Note
 * that this only applies for non anonymizable field backups.
 *
 * @author Viktor Balogh (Wiera)
 * @author Marcus St&auml;nder | Trusted Mails GmbH | <a href="mailto:marcus.staender@trusted-mails.com">marcus.staender@trusted-mails.com</a>
 */
class FieldBackup
{

    public $type;
    public $subtype;
    public $name;
    public $option;
    public $value;

    /**
     * Constructor initializing a field backup
     *
     * @param String $type
     * Indicates the type of the contact field. Supported values are: "standard", "custom" (custom contact fields) and "event" (contact event properties)
     * @param String $subtype
     * Backup instructions of type 'event' require a subtype field containing the event type
     * @param String $name
     * The name of the field
     * @param String $option
     * @param String $value
     * the value of the backuped field
     */
    function __construct($type, $subtype, $name, $option, $value)
    {
        $this->type = $type;
        $this->subtype = $subtype;
        $this->name = $name;
        $this->option = $option;
        $this->value = $value;
    }
}

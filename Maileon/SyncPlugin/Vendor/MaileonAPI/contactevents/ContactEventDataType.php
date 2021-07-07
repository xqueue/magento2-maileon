<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\contactevents;

/**
 * A type descriptor class for attribute definitions.
 *
 * The supported data types are string, double, float, integer, boolean and timestamp.
 *
 * @author Marcus St&auml;nder | Trusted Mails GmbH | <a href="mailto:marcus.staender@trusted-mails.com">marcus.staender@trusted-mails.com</a>
 */
class ContactEventDataType
{
    public static $STRING;
    public static $DOUBLE;
    public static $FLOAT;
    public static $INTEGER;
    public static $BOOLEAN;
    public static $TIMESTAMP;

    private static $initialized = false;

    // TODO use a more sensible name for this concept, e.g. "type descriptor"
    /**
     *
     * @var string $value
     *  A string that describes the datatype. Valid values are "string", "double", "float",
     *  "integer", "boolean" and "timestamp".
     */
    public $value;

    static function init()
    {
        if (self::$initialized == false) {
            self::$STRING = new ContactEventDataType("string");
            self::$DOUBLE = new ContactEventDataType("double");
            self::$FLOAT = new ContactEventDataType("float");
            self::$INTEGER = new ContactEventDataType("integer");
            self::$BOOLEAN = new ContactEventDataType("boolean");
            self::$TIMESTAMP = new ContactEventDataType("timestamp");
            self::$initialized = true;
        }
    }

    /**
     * Creates a new ContactEventDataType object.
     *
     * @param string $value
     *  a string describing the data type. Valid values are "string", "double", "float",
     *  "integer", "boolean" and "timestamp".
     */
    function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return \em string
     *  the type descriptor string of this ContactEventDataType. Can be "string", "double", "float",
     *  "integer", "boolean" or "timestamp".
     */
    function getValue()
    {
        return $this->value;
    }

    /**
     * Get the permission object by type descriptor.
     *
     * @param string $value
     *  a type descriptor string. Can be "string", "double", "float",
     *  "integer", "boolean" or "timestamp".
     * @return \em com_maileon_api_contacts_Permission
     *  the permission object
     */
    static function getDataType($value)
    {
        switch ($value) {
            case "string":
                return self::$STRING;
            case "double":
                return self::$DOUBLE;
            case "float":
                return self::$FLOAT;
            case "integer":
                return self::$INTEGER;
            case "boolean":
                return self::$BOOLEAN;
            case "timestamp":
                return self::$TIMESTAMP;

            default:
                return null;
        }
    }
}

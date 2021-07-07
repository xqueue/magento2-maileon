<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck RiskType
 */
Risk::init();
class Risk {
	public static $UNKNOWN;
	public static $DOMAIN;
	public static $MAILBOX;
        public static $ADDRESS;
        public static $REGEXP;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$UNKNOWN = new Risk(0);
                        self::$DOMAIN = new Risk(1);
			self::$MAILBOX = new Risk(2);
                        self::$ADDRESS = new Risk(3);
                        self::$REGEXP = new Risk(4);
			self::$initialized = true;
		}
	}

	/**
	 * Constructor initializing default values.
	 *
	 * @param number $code
	 */
	function __construct($value) {
		$this->value = $value;
	}

	/**
	 * Get the code of this risk type
	 *
	 */
	function getValue() {
		return $this->value;
	}

	/**
	 * Get the verifiable object from the code
	 *
	 * @param int $code
	 * @return Risk
	 */
	static function getRiskType($value) {
		switch ($value) {
			case 0:	return self::$UNKNOWN;
			case 1:	return self::$DOMAIN;
			case 2:	return self::$MAILBOX;
                        case 3:	return self::$ADDRESS;
                        case 4:	return self::$REGEXP;

			default: return null;
		}
	}

        function __toString() {
                return $this->toString();
        }

        public function toString()
        {
                switch ($this->value) {
			case 0:	return "UNKNOWN";
			case 1:	return "DOMAIN";
			case 2:	return "MAILBOX";
                        case 3:	return "ADDRESS";
                        case 4:	return "REGEXP";

			default: return null;
		}
        }
}

<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck LanguageOrientationType
 */
LanguageOrientation::init();
class LanguageOrientation {
	public static $UNKNOWN;
	public static $NATIONAL;
	public static $INTERNATIONAL;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$UNKNOWN = new LanguageOrientation(0);
			self::$NATIONAL = new LanguageOrientation(1);
			self::$INTERNATIONAL = new LanguageOrientation(2);
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
	 * Get the code of this list type
	 *
	 */
	function getValue() {
		return $this->value;
	}

	/**
	 * Get the verifiable object from the code
	 *
	 * @param int $code
	 * @return LanguageOrientation
	 */
	static function getLanguageOrientation($value) {
		switch ($value) {
			case 0:	return self::$UNKNOWN;
			case 1:	return self::$NATIONAL;
			case 2:	return self::$INTERNATIONAL;

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
			case 1:	return "NATIONAL";
			case 2:	return "INTERNATIONAL";

			default: return null;
		}
        }
}

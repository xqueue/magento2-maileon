<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Calss for parsing AddressCheck MailserverDiagnostics
 */
MailserverDiagnostics::init();
class MailserverDiagnostics {
    public static $UNKNOWN;
	public static $TRUTH;
	public static $ALWAYS_CONFIRMS;
	public static $ALWAYS_DENIES;
    public static $ERROR;
    public static $GREYLISTING;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
            self::$UNKNOWN = new MailserverDiagnostics(0);
			self::$TRUTH = new MailserverDiagnostics(1);
			self::$ALWAYS_CONFIRMS = new MailserverDiagnostics(2);
			self::$ALWAYS_DENIES = new MailserverDiagnostics(3);
			self::$ERROR = new MailserverDiagnostics(4);
            self::$GREYLISTING = new MailserverDiagnostics(6);
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
	 * Get the code of this diagnosis type
	 *
	 */
	function getValue() {
		return $this->value;
	}

	/**
	 * Get the diagnosis object from the code
	 *
	 * @param int $code
	 * @return MailserverDiagnostics
	 */
	static function getDiagnosisType($value) {
		switch ($value) {
                        case 0: return self::$UNKNOWN;
			case 1:	return self::$TRUTH;
			case 2:	return self::$ALWAYS_CONFIRMS;
			case 3:	return self::$ALWAYS_DENIES;
			case 4:	return self::$ERROR;
			case 6:	return self::$GREYLISTING;
			default: return null;
		}

	}

        function __toString() {
                return $this->toString();
        }

        public function toString() {
                switch ($this->value) {
                        case 0:	return "UNKNOWN";
                        case 1:	return "TRUTH";
                        case 2:	return "ALWAYS CONFIRMS";
                        case 3:	return "ALWAYS DENIES";
                        case 4:	return "ERROR";
			case 6:	return "GREYLISTING";

			default: return null;
		}
        }

}

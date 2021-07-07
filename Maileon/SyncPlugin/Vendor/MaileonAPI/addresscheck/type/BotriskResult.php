<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck BotriskResult
 */
BotriskResult::init();
class BotriskResult {
	public static $ERROR;
	public static $NORMAL;
	public static $MODERATE;
    public static $INCREASED;
    public static $HIGH;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$ERROR = new BotriskResult(-1);
			self::$NORMAL = new BotriskResult(0);
			self::$INCREASED = new BotriskResult(10);
                        self::$MODERATE = new BotriskResult(20);
                        self::$HIGH = new BotriskResult(30);
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
	 * @return BotriskType
	 */
	static function getBotriskType($value) {
		switch ($value) {
                        case -1: return self::$ERROR;
                        case  0: return self::$NORMAL;
                        case 10: return self::$INCREASED;
                        case 20: return self::$MODERATE;
                        case 30: return self::$HIGH;

			default: return null;
		}
	}

        function __toString() {
                return $this->toString();
        }

        public function toString()
        {
                switch ($this->value) {
			case -1: return "ERROR";
                        case  0: return "NORMAL";
                        case 10: return "INCREASED";
                        case 20: return "MODERATE";
                        case 30: return "HIGH";

			default: return null;
		}
        }
}

<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck GenderDetailType
 */
GenderDetail::init();
class GenderDetail {
	public static $FEMININE;
	public static $MOSTLY_FEMININE;
	public static $MASCULINE;
        public static $MOSTLY_MASCULINE;
        public static $UNISEX;
        public static $NOT_RECOGNIZED;
        public static $INPUT_ERROR;
        public static $INVALID_ADDRESS;
        public static $PROCESSING_ERROR;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$FEMININE = new GenderDetail(1);
			self::$MOSTLY_FEMININE = new GenderDetail(2);
			self::$MASCULINE = new GenderDetail(3);
                        self::$MOSTLY_MASCULINE = new GenderDetail(4);
                        self::$UNISEX = new GenderDetail(5);
                        self::$NOT_RECOGNIZED = new GenderDetail(6);
                        self::$INPUT_ERROR = new GenderDetail(7);
                        self::$INVALID_ADDRESS = new GenderDetail(8);
                        self::$PROCESSING_ERROR = new GenderDetail(9);
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
	 * @return GenderDetail
	 */
	static function getGenderDetail($value) {
		switch ($value) {
                        case 1: return self::$FEMININE;
                        case 2: return self::$MOSTLY_FEMININE;
                        case 3: return self::$MASCULINE;
                        case 4: return self::$MOSTLY_MASCULINE;
                        case 5: return self::$UNISEX;
                        case 6: return self::$NOT_RECOGNIZED;
                        case 7: return self::$INPUT_ERROR;
                        case 8: return self::$INVALID_ADDRESS;
                        case 9: return self::$PROCESSING_ERROR;

			default: return null;
		}
	}

        function __toString() {
                return $this->toString();
        }

        public function toString()
        {
                switch ($this->value) {
			case 1: return "FEMININE";
                        case 2: return "MOSTLY FEMININE";
                        case 3: return "MASCULINE";
                        case 4: return "MOSTLY MASCULINE";
                        case 5: return "UNISEX";
                        case 6: return "NOT RECOGNIZED";
                        case 7: return "INPUT ERROR";
                        case 8: return "INVALID ADDRESS";
                        case 9: return "PROCESSING ERROR";

			default: return null;
		}
        }
}

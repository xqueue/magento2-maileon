<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck Verifiable Type
 */
Verifiable::init();
class Verifiable {
	public static $NO;
	public static $YES;
	public static $NOT_VERIFIABLE;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$NO = new Verifiable(0);
			self::$YES = new Verifiable(1);
			self::$NOT_VERIFIABLE = new Verifiable(2);
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
	 * Get the code of this verifiable type
	 *
	 */
	function getValue() {
		return $this->value;
	}

	/**
	 * Get the verifiable object from the code
	 *
	 * @param int $code
	 * @return Verifiable
	 */
	static function getVerifiableType($value) {
		switch ($value) {
			case 0:	return self::$NO;
			case 1:	return self::$YES;
			case 2:	return self::$NOT_VERIFIABLE;

			default: return null;
		}
	}

        function __toString() {
                return $this->toString();
        }

        public function toString()
        {
                switch ($this->value) {
			case 0:	return "NO";
			case 1:	return "YES";
			case 2:	return "NOT VERIFIABLE";

			default: return null;
		}
        }
}

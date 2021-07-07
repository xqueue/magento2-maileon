<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck SyntaxType responses
 */
Syntax::init();
class Syntax {
	public static $NO;
	public static $YES;
	public static $DECODED;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$NO = new Syntax(0);
			self::$YES = new Syntax(1);
			self::$DECODED = new Syntax(2);
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
	 * Get the code of this syntax type
	 *
	 */
	function getValue() {
		return $this->value;
	}

	/**
	 * Get the syntax object from the code
	 *
	 * @param int $code
	 * @return Syntax
	 */
	static function getSyntaxType($value) {
		switch ($value) {
			case 0:	return self::$NO;
			case 1:	return self::$YES;
			case 2:	return self::$DECODED;

			default: return null;
		}
	}

        public function __toString() {
		switch ($this->value) {
			case 0:	return "NO";
			case 1:	return "YES";
			case 2:	return "DECODED";

			default: return null;
		}
	}
}

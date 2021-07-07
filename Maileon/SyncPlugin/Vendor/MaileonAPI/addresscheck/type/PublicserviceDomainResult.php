<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type;

/**
 * Class for parsing AddressCheck PublicserviceDomainResultType
 */
PublicserviceDomainResult::init();
class PublicserviceDomainResult {
	public static $NO;
	public static $PROBABLY;
	public static $DEFINITELY;

	private static $initialized = false;

	public $value;

	static function init() {
		if (self::$initialized == false) {
			self::$NO = new PublicserviceDomainResult(0);
			self::$PROBABLY = new PublicserviceDomainResult(50);
			self::$DEFINITELY = new PublicserviceDomainResult(100);
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
	 * @return PublicserviceDomainResult
	 */
	static function getPublicserviceDomainResult($value) {
		switch ($value) {
                        case 0: return self::$NO;
                        case 50: return self::$PROBABLY;
                        case 100: return self::$DEFINITELY;

			default: return null;
		}
	}

        function __toString() {
                return $this->toString();
        }

        public function toString()
        {
                switch ($this->value) {
                        case 0: return "NO";
                        case 50: return "PROBABLY";
                        case 100: return "DEFINITELY";

			default: return null;
		}
        }
}

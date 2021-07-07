<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck;

use Maileon\SyncPlugin\Vendor\MaileonAPI\AbstractMaileonService;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\Syntax;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\MailserverDiagnostics;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\Verifiable;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\Risk;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\GenderDetail;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\LanguageOrientation;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\PublicserviceDomainResult;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\BotriskResult;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\SyntaxWarnings;

/**
 * Class for using the AddressCheck service
 */
class AddressCheckService extends AbstractMaileonService
{
        /**
         * Mime type for the AddressCheck xml data format.
         *
         * @var string
         */
        public static $ADDRESSCHECK_XML_MIME_TYPE = 'application/xml';

        public static function init()
        {
                Syntax::init();
                MailserverDiagnostics::init();
                Verifiable::init();
                Risk::init();
                GenderDetail::init();
                LanguageOrientation::init();
                PublicserviceDomainResult::init();
                BotriskResult::init();

                SyntaxWarnings::init();
        }

        /**
         * Checks the quality of an e-mail address.
         *
         * The order in which the tests are performed is the following:
         * - syntax
         * - extsyntax
         * - domain
         * - mailserver
         * - mailserverDiagnosis
         * - bouncerisk
         * - probability
         * - checked
         * - address
         *
         * @param string $email
         * @return MaileonAPIResult
         */
        function CheckAddressQuality($email)
        {
            return $this->get("2.0/address/quality/" . urlencode($email), "", self::$ADDRESSCHECK_XML_MIME_TYPE);
        }

        function CheckAddressSyntax($email)
        {
            return $this->get("2.0/address/syntax/" . urlencode($email), "", self::$ADDRESSCHECK_XML_MIME_TYPE);
        }
}

?>

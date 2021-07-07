<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\address;

use Maileon\SyncPlugin\Vendor\MaileonAPI\xml\AbstractXMLWrapper;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\Syntax;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\MailserverDiagnostics;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\Verifiable;

/**
 * Class for parsing AddressCheck Quality Check response
 */
class QualityStatus extends AbstractXMLWrapper
{
    /**
     * Is the e-mail syntactically correct
     * - $NO - No
     * - $YES - Yes
     * - $DECODED - Yes, but unicode characters have been decoded
     *
     * If this is $NO or $DECODED, $syntaxWarnings may contain messages in the set
     * locale to help narrow down the error.
     *
     * If this is $DECODED, $decoded will containt the decoded address
     *
     * @var Syntax
     */
    public $syntax;

    /**
     * Is the e-mail address correct according to the provider-specific
     * extended syntax rules.
     *
     * If it isn't, $syntaxWarnings may contain messages in the set
     * locale to help narrow down the error.
     *
     * @var bool
     */
    public $extsyntax;

    /**
     * The domain of the address is available in the DNS
     * In the negative case follows a list of similar domain names
     * that can be displayed to the user.
     *
     * @var bool
     */
    public $domain;

    /**
     * Does the domain have DNS entries for the mail server
     *
     * @var bool
     */
    public $mailserver;

    /**
     * Response of the respective mail server
     * - $TRUTH;
     * - $ALWAYS_CONFIRMS;
     * - $ALWAYS_DENIES;
     * - $ERROR_IF_MISSING;
     *
     * @var MailserverDiagnostics
     */
    public $mailserverDiagnostics;

    /**
     * The aggregated bounce risk for emails to this domain
     * - 1 - normal
     * - 0 - high
     *
     * @var int
     */
    public $bouncerisk;

    /**
     * The probability that this domain was meant by the user
     * - 1 - most likely
     * - 0 - not likely
     *
     * In the negative case, a list of probable domains is appended,
     * sorted by score in $domainScores
     *
     * @var int
     */
    public $probability;

    /**
     * Provides information about whether the test was actually performed,
     * or was in the cache
     * - true - the mail server test was carried out directly
     * - false - the response was from the cache
     *
     * @var bool
     */
    public $checked;

    /**
     * The e-mail address is available according to the mail servers
     * - $NO;
     * - $YES;
     * - $NOT_VERIFIABLE;
     *
     * @var Verifiable
     */
    public $address;


    /**
     * The decoded email address, if there was conversion
     *
     * @var string
     */
    public $decoded;


    /**
     * Array of suggested domain names paired with scores, eg.
     * - $domainScores[0]['name']
     * - $domainScores[0]['score']
     *
     * @var array
     */
    public $domainScores;

    /**
     * Syntax warnings in the given LOCALE
     *
     * @var array
     */
    public $syntaxWarnings;

    function __construct()
    {
        $this->syntax = null;
        $this->extsyntax = null;
        $this->domain = null;
        $this->mailserver = null;
        $this->mailserverDiagnostics = null;
        $this->bouncerisk = null;
        $this->probability = null;
        $this->checked = null;
        $this->address = null;
        $this->decoded = null;
        $this->domainScores = array();
        $this->syntaxWarnings = array();
    }

    /**
    * Initialization from a simple xml element.
    *
    * @param \SimpleXMLElement $xmlElement
    */
    public function fromXML($xmlElement) {

        $this->syntax = Syntax::getSyntaxType( (int)$xmlElement->syntax );
        $this->mailserverDiagnostics = MailserverDiagnostics::getDiagnosisType( (int)$xmlElement->mailserverDiagnosis );
        $this->address = Verifiable::getVerifiableType( (int)$xmlElement->address );

        $this->extsyntax = (bool)(int)$xmlElement->extsyntax;
        $this->domain = (bool)(int)$xmlElement->domain;
        $this->mailserver = (bool)(int)$xmlElement->mailserver;
	$this->checked = (bool)(int)$xmlElement->checked;

        $this->bouncerisk = (int)$xmlElement->bouncerisk;
        $this->probability = (int)$xmlElement->probability;

        if(isset($xmlElement->decoded))
        {
            $this->decoded = (string)$xmlElement->decoded;
        }

        if(isset($xmlElement->domainScores))
        {
            $this->domainScores = array();
            foreach ($xmlElement->domainScores->children() as $field) {
                $this->domainScores[] = array(
                        'score' => (string)$field->score,
                        'name' => (string)$field->domain);
            }
        }

        if( isset($xmlElement->syntaxWarnings))
        {
            foreach ($xmlElement->syntaxWarnings as $warning) {
                $this->syntaxWarnings[]= $warning;
            }
        }
    }

    function __toString() {
            return $this->toString();
    }

    function toString() {

        $result = get_class() . " (\n"
                . "\t syntax=" . $this->syntax . ", \n"
                . "\t mailserverDiagnostics=" . $this->mailserverDiagnostics . ", \n"
                . "\t address=" . $this->address . ", \n"
                . "\t extsyntax=" . $this->extsyntax . ", \n"
                . "\t domain=" . $this->domain . ", \n"
                . "\t mailserver=" . $this->mailserver . ", \n"
                . "\t checked=" . $this->checked . ", \n"
                . "\t bouncerisk=" . $this->bouncerisk . ", \n"
                . "\t probability=" . $this->probability . ", \n"
                . "\t decoded=" . $this->decoded;

        if(isset($this->domainScores)) {

                $warnings = array();
                foreach ($this->domainScores as $field) {
                        $warnings []= "[" . $field['score'] . "]=>" . $field['name'];
                }

                $result .= ", \n \t domainScores=array(\n \t\t"
                        . implode(", \n \t\t", $warnings)
                        . ")";
        }

        if(isset($this->syntaxWarnings)) {
                $result .= ", \n \t syntaxWarnings=array(\n \t\t"
                        . html_entity_decode(implode(", \n \t\t",  $this->syntaxWarnings ) , ENT_QUOTES, "UTF-8")
                        . ")";
        }

        $result .= ")";

        return $result;
    }
    function toXML() { }
}
?>

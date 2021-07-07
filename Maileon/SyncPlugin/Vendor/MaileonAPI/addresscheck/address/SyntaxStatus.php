<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\address;

use Maileon\SyncPlugin\Vendor\MaileonAPI\xml\AbstractXMLWrapper;
use Maileon\SyncPlugin\Vendor\MaileonAPI\addresscheck\type\Syntax;

/**
 * Class for parsing AddressCheck Syntax Check response
 */
class SyntaxStatus extends AbstractXMLWrapper
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
    public $result;

    /**
     * Is the e-mail address correct according to the provider-specific
     * extended syntax rules.
     *
     * If it isn't, $syntaxWarnings may contain messages in the set
     * locale to help narrow down the error.
     *
     * @var bool
     */
    public $extSyntax;

    /**
     * The decoded email address, if there was conversion
     *
     * @var string
     */
    public $decoded;

    /**
     * Syntax warning codes
     *
     * @var array
     */
    public $syntaxWarnings;

    /**
     * Array of suggested domain names paired with scores, eg.
     * - $domainScores[0]['name']
     * - $domainScores[0]['score']
     *
     * @var array
     */
    public $domainScores;

    function __construct()
    {
        $this->result = null;
        $this->extSyntax = null;

        $this->decoded = null;
        $this->syntaxWarnings = array();
        $this->domainScores = array();
    }

    /**
    * Initialization from a simple xml element.
    *
    * @param \SimpleXMLElement $xmlElement
    */
    public function fromXML($xmlElement) {
        $this->result = Syntax::getSyntaxType( (int)$xmlElement->result );
        $this->extSyntax = (bool)(int)$xmlElement->extSyntax;

        if(isset($xmlElement->decoded)) $this->decoded = (string)$xmlElement->decoded;

        if(isset($xmlElement->syntaxWarnings))
        {
            foreach ($xmlElement->syntaxWarnings->children() as $warning) {
                $this->syntaxWarnings[]= (string)$warning;
            }
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
    }

    function __toString() {
        return $this->toString();
    }

    function toString() {
        $result = get_class() . " (\n"
                . "\t result=" . $this->result . ", \n"
                . "\t decoded=" . $this->decoded . ", \n"
                . "\t extSyntax=" . $this->extSyntax;

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

<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\reports;

use Maileon\SyncPlugin\Vendor\MaileonAPI\xml\AbstractXMLWrapper;
use Maileon\SyncPlugin\Vendor\MaileonAPI\reports\ReportContact;

/**
 * This class represents a unique bounce containing the timestamp, the contact, and the ID of the mailing.
 *
 * @author Marcus St&auml;nder
 */
class UniqueBounce extends AbstractXMLWrapper
{
    /**
     * @var String
     */
    public $timestamp;

    /**
     * @var ReportContact
     */
    public $contact;

    /**
     * @var long
     */
    public $mailingId;


    /**
     * @var String
     */
    public $lastType;

    /**
     * @var int
     */
    public $count;

    /**
     * @var int
     */
    public $countHard;

    /**
     * @var int
     */
    public $countSoft;

    /**
     * @return \em string
     *  containing a human-readable representation of this unique bounce
     */
    function toString()
    {
        return "UniqueBounce [timestamp=" . $this->timestamp .
        ", contact=" . $this->contact->toString() .
        ", mailingId=" . $this->mailingId .
        ", count=" . $this->count .
        ", countHard=" . $this->countHard .
        ", countSoft=" . $this->countSoft .
        ", lastType=" . $this->lastType . "]";
    }

    /**
     * @return \em csv string
     *  containing a csv pepresentation of this unique bounce
     */
    function toCsvString()
    {
        return $this->timestamp .
        ";" . $this->contact->toCsvString() .
        ";" . $this->mailingId .
        ";" . $this->count .
        ";" . $this->countHard .
        ";" . $this->countSoft .
        ";" . $this->lastType;
    }

    /**
     * Initializes this unique bounce from an XML representation.
     *
     * @param SimpleXMLElement $xmlElement
     *  the XML representation to use
     */
    function fromXML($xmlElement)
    {
        $this->contact = new ReportContact();
        $this->contact->fromXML($xmlElement->contact);

        if (isset($xmlElement->mailing_id)) $this->mailingId = $xmlElement->mailing_id;
        if (isset($xmlElement->timestamp)) $this->timestamp = $xmlElement->timestamp;
        if (isset($xmlElement->last_type)) $this->lastType = $xmlElement->last_type;
        if (isset($xmlElement->count)) $this->count = $xmlElement->count;
        if (isset($xmlElement->count_hard)) $this->countHard = $xmlElement->count_hard;
        if (isset($xmlElement->count_soft)) $this->countSoft = $xmlElement->count_soft;
    }

    /**
     * For future use, not implemented yet.
     *
     * @return \em SimpleXMLElement
     *  containing the XML serialization of this object
     */
    function toXML()
    {
        // Not implemented yet.
    }
}

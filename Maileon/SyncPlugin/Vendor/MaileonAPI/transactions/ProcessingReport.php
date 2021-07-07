<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\transactions;

use Maileon\SyncPlugin\Vendor\MaileonAPI\json\AbstractJSONWrapper;
use Maileon\SyncPlugin\Vendor\MaileonAPI\transactions\ReportContact;

/**
 * A wrapper class for a single transaction processing report
 *
 * @author Balogh Viktor <balogh.viktor@maileon.hu> | Maileon - Wanadis Kft.
 */

class ProcessingReport extends AbstractJSONWrapper {
    /**
     * The contact this transaction was sent to
     *
     * @var com_maileon_api_transactions_ReportContact
     */
    public $contact;

    /**
     * Whether this transaction was succesfully queued
     *
     * @var boolean
     */
    public $queued;

    /**
     * The error message (if there was any)
     *
     * @var string
     */
    public $message;

    function __construct() {
        $this->contact = new ReportContact();
    }
}

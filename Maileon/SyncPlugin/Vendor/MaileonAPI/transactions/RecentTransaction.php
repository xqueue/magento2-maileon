<?php

namespace Maileon\SyncPlugin\Vendor\MaileonAPI\transactions;

use Maileon\SyncPlugin\Vendor\MaileonAPI\json\AbstractJSONWrapper;

/**
 * A class for wrapping a recent transaction.
 *
 * @author Viktor Balogh | Wanadis Kft. | <a href="balogh.viktor@maileon.hu">balogh.viktor@maileon.hu</a>
 */

class RecentTransaction extends AbstractJSONWrapper {
    /**
     * The data for the transaction
     *
     * @var array
     */
    public $tx;
    /**
     * The id for this data
     *
     * @var int
     */
    public $txId;
    /**
     * The contact id
     *
     * @var id
     */
    public $contactId;
    /**
     * The email address
     *
     * @var string
     */
    public $email;

    /**
     * @return \em string
     *    a human-readable representation of this recent transaction
     */
    function toString() {
        return parent::__toString();
    }
}

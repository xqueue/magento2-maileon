<?php
namespace Xqueue\Maileon\Model\ResourceModel\MaileonLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'xqueue_maileon_log_collection';
    protected $_eventObject = 'log_collection';
    
    protected function _construct(): void
    {
        $this->_init(
            \Xqueue\Maileon\Model\MaileonLog::class,
            \Xqueue\Maileon\Model\ResourceModel\MaileonLog::class
        );
    }
}

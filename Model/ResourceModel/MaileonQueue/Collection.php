<?php
namespace Xqueue\Maileon\Model\ResourceModel\MaileonQueue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'xqueue_maileon_queue_collection';
    protected $_eventObject = 'queue_collection';

    protected function _construct(): void
    {
        $this->_init(
            \Xqueue\Maileon\Model\MaileonQueue::class,
            \Xqueue\Maileon\Model\ResourceModel\MaileonQueue::class
        );
    }
}

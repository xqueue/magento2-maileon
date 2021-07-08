<?php
namespace Xqueue\Maileon\Model\ResourceModel\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'maileon_syncplugin_queue_collection';
    protected $_eventObject = 'queue_collection';

    protected function _construct()
    {
        $this->_init('Xqueue\Maileon\Model\Queue', 'Xqueue\Maileon\Model\ResourceModel\Queue');
    }
}

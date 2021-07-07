<?php
namespace Maileon\SyncPlugin\Model\ResourceModel\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'maileon_syncplugin_queue_collection';
    protected $_eventObject = 'queue_collection';

    protected function _construct()
    {
        $this->_init('Maileon\Syncplugin\Model\Queue', 'Maileon\Syncplugin\Model\ResourceModel\Queue');
    }
}

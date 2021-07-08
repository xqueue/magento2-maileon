<?php
namespace Xqueue\Maileon\Model\ResourceModel\Log;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'maileon_syncplugin_log_collection';
    protected $_eventObject = 'log_collection';
    
    protected function _construct()
    {
        $this->_init('Xqueue\Maileon\Model\Log', 'Xqueue\Maileon\Model\ResourceModel\Log');
    }
}

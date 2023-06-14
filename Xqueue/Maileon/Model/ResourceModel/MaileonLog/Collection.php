<?php
namespace Xqueue\Maileon\Model\ResourceModel\MaileonLog;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'maileon_syncplugin_log_collection';
    protected $_eventObject = 'log_collection';
    
    protected function _construct()
    {
        $this->_init('Xqueue\Maileon\Model\MaileonLog', 'Xqueue\Maileon\Model\ResourceModel\MaileonLog');
    }
}

<?php
namespace Xqueue\Maileon\Model\ResourceModel;

class MaileonQueue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    ) {
        parent::__construct($context);
    }
    
    protected function _construct()
    {
        $this->_init('maileon_queue', 'id');
    }
}

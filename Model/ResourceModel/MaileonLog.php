<?php
namespace Xqueue\Maileon\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class MaileonLog extends AbstractDb
{
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }
    
    protected function _construct(): void
    {
        $this->_init('maileon_log', 'id');
    }
}

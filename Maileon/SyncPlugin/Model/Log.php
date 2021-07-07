<?php
namespace Maileon\SyncPlugin\Model;

class Log extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'maileon_syncplugin_log';

    protected $_cacheTag = 'maileon_syncplugin_log';

    protected $_eventPrefix = 'maileon_syncplugin_log';
    
    protected function _construct()
    {
        $this->_init('Maileon\SyncPlugin\Model\ResourceModel\Log');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }
}

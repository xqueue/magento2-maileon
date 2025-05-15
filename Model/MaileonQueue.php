<?php
namespace Xqueue\Maileon\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class MaileonQueue extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'xqueue_maileon_queue';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'xqueue_maileon_queue';

    protected function _construct(): void
    {
        $this->_init(\Xqueue\Maileon\Model\ResourceModel\MaileonQueue::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues(): array
    {
        return [];
    }
}

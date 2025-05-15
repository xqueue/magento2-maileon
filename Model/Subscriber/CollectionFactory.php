<?php

namespace Xqueue\Maileon\Model\Subscriber;

use Magento\Framework\ObjectManagerInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection;

class CollectionFactory
{
    public function __construct(private ObjectManagerInterface $objectManager) {}

    public function create(): Collection
    {
        return $this->objectManager->create(Collection::class);
    }
}
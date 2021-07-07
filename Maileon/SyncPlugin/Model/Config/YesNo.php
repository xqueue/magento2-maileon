<?php

namespace Maileon\SyncPlugin\Model\Config;

class YesNo implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
        ['value' => 'no', 'label' => 'No'],
        ['value' => 'yes', 'label' => 'Yes']
        ];
    }
}

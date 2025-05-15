<?php

namespace Xqueue\Maileon\Model\Config;

use Magento\Framework\Data\OptionSourceInterface;

class ListPermission implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => 'None'],
            ['value' => 'soi', 'label' => 'Single Opt-in'],
            ['value' => 'coi', 'label' =>'Confirmed Opt-in'],
            ['value' => 'doi', 'label' => 'Double Opt-in'],
            ['value' => 'doi+', 'label' => 'Double Opt-in Plus']
        ];
    }
}

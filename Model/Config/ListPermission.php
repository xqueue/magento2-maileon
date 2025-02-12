<?php

namespace Xqueue\Maileon\Model\Config;

class ListPermission implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
        ['value' => 'none', 'label' => 'None'],
        ['value' => 'single_optin', 'label' => 'Single Opt-in'],
        ['value' => 'confirmed_optin', 'label' =>'Confirmed Opt-in'],
        ['value' => 'double_optin', 'label' => 'Double Opt-in'],
        ['value' => 'double_optin_plus', 'label' => 'Double Opt-in Plus']
        ];
    }
}

<?php
namespace SVExtensions\DynamicLabel\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ValueType implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'plain', 'label' => __('Plain Value')],
            ['value' => 'html', 'label' => __('HTML Value')],
        ];
    }
}
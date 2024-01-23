<?php

namespace OneO\Shop\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * TriggerOptions class
 */
class TriggerOptions implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     * @return array|\string[][]
     */
    public function toOptionArray()
    {

        return [
            [
                'value' => 'new',
                'label' => 'Created',
            ],
            [
                'value' => 'processing',
                'label' => 'Processing',
            ],
            [
                'value' => 'complete',
                'label' => 'Complete',
            ],
        ];
    }
}
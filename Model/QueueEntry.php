<?php

namespace OneO\Shop\Model;

/**
 * QueueEntry class
 */
class QueueEntry extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(\OneO\Shop\Model\ResourceModel\QueueEntry::class);
    }
}

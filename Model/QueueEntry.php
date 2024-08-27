<?php

namespace Katalys\Shop\Model;

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
        $this->_init(\Katalys\Shop\Model\ResourceModel\QueueEntry::class);
    }
}

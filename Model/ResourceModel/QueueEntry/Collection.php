<?php

namespace Katalys\Shop\Model\ResourceModel\QueueEntry;

/**
 * Collection class
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = '_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'revoffers_advertiserintegration_queueentry_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'queueentry_collection';

    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            \Katalys\Shop\Model\QueueEntry::class,
            \Katalys\Shop\Model\ResourceModel\QueueEntry::class
        );
    }
}

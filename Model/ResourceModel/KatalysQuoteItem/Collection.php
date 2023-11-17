<?php

namespace OneO\Shop\Model\ResourceModel\KatalysQuoteItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @inheridoc
     */
    public function _construct()
    {
        $this->_init(
            \OneO\Shop\Model\KatalysQuoteItem::class,
            \OneO\Shop\Model\ResourceModel\KatalysQuoteItem::class
        );
    }
}

<?php

namespace Katalys\Shop\Model\ResourceModel\KatalysQuoteItem;

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
            \Katalys\Shop\Model\KatalysQuoteItem::class,
            \Katalys\Shop\Model\ResourceModel\KatalysQuoteItem::class
        );
    }
}

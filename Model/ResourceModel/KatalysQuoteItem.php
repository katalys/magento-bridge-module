<?php

namespace OneO\Shop\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;

/**
 * Class KatalysQuoteItem
 */
class KatalysQuoteItem extends AbstractDb
{

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('katalys_quote_item', 'entity_id');
    }
}

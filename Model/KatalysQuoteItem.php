<?php

namespace Katalys\Shop\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class KatalysQuoteItem
 */
class KatalysQuoteItem extends AbstractModel
{
    /**
     * Init resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Katalys\Shop\Model\ResourceModel\KatalysQuoteItem::class);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: lyamada
 * Date: 2019-07-31
 * Time: 16:10
 */

namespace Katalys\Shop\Model\ResourceModel;

/**
 * QueueEntry class
 */
class QueueEntry extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init('katalys_ad_queue', '_id');
    }
}

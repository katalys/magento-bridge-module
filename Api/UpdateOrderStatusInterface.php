<?php

namespace Katalys\Shop\Api;

/**
 * UpdateOrderStatusInterface interface
 */
interface UpdateOrderStatusInterface
{
    /**
     * @api
     * @param string $id
     * @return array
     */
    public function update($id);
}
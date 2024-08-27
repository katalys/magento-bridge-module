<?php

namespace Katalys\Shop\Api;

/**
 * RecordOrderInterface interface
 */
interface RecordOrderInterface
{
    /**
     * @api
     * @param string $id
     * @return array
     */
    public function send($id);
}
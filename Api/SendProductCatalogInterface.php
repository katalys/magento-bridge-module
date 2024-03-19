<?php

namespace Katalys\Shop\Api;

/**
 * SendProductCatalogInterface interface
 */
interface SendProductCatalogInterface
{
    /**
     * @api
     * @return array
     */
    public function send();
}
<?php

declare(strict_types=1);

namespace OneO\Shop\Model;

use OneO\Shop\Api\Data\ProcessDirectiveInterface;

class ProcessHealthCheckDirective implements ProcessDirectiveInterface
{
    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): array
    {
        return ['status' => 'ok'];
    }
}
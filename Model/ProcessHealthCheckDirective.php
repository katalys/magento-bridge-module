<?php

declare(strict_types=1);

namespace Katalys\Shop\Model;

use Katalys\Shop\Api\Data\ProcessDirectiveInterface;

/**
 * ProcessHealthCheckDirective class
 */
class ProcessHealthCheckDirective implements ProcessDirectiveInterface
{
    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): array
    {
        return [
            'status' => 'ok',
            'data' => [
                'healthy' => true,
                'internal_error' => null,
                'public_error' => null,
                'name' => 'Katalys Magento integration',
                'implemented_directives' => [
                    "health_check",
                    "update_available_shipping_rates",
                    "update_tax_amounts",
                    "update_availability",
                    "complete_order",
                    "import_product_from_url",
                    "product_information_sync"
                ]
            ]
        ];
    }
}
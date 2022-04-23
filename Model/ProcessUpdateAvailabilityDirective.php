<?php

declare(strict_types=1);

namespace OneO\Shop\Model;

use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use OneO\Shop\Api\Data\ProcessDirectiveInterface;

class ProcessUpdateAvailabilityDirective implements ProcessDirectiveInterface
{
    const ORDER_ID_KEY = 'order_id';
    private OneOGraphQLClient $graphQLClient;
    private \Magento\CatalogInventory\Api\StockStatusRepositoryInterface $stockStatusRepository;

    /**
     * @param OneOGraphQLClient $graphQLClient
     * @param \Magento\CatalogInventory\Api\StockStatusRepositoryInterface $stockStatusRepository
     */
    public function __construct(
        \OneO\Shop\Model\OneOGraphQLClient $graphQLClient,
        \Magento\CatalogInventory\Api\StockStatusRepositoryInterface $stockStatusRepository
    )
    {
        $this->graphQLClient = $graphQLClient;
        $this->stockStatusRepository = $stockStatusRepository;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): array
    {
        $arguments = $jsonDirective[self::ARGS_KEY];
        $orderId = $arguments[self::ORDER_ID_KEY];

        $graphQlClient = $this->graphQLClient->getClient();
        $oneOOrder = $graphQlClient->getOrderDetails($orderId);

        $itemAvailabilities = [];
        foreach ($oneOOrder["lineItems"] as $oneOLineItem)
        {
            $magentoProductId = $oneOLineItem["variantExternalId"] ?? $oneOLineItem["productExternalId"];
            $stockStatus = $this->stockStatusRepository->get($magentoProductId);
            $productStockStatus = (int)$stockStatus->getStockStatus();

            $itemAvailabilities[] = [
                "id" => $oneOLineItem["id"],
                "available" => $productStockStatus === StockStatusInterface::STATUS_IN_STOCK,
            ];
        }

        $graphQlClient = $this->graphQLClient->getClient();
        $graphQlClient->updateAvailabilities($itemAvailabilities);

        return ['status' => 'ok'];
    }
}
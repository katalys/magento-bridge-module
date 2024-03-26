<?php

declare(strict_types=1);

namespace Katalys\Shop\Model;

use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Katalys\Shop\Api\Data\ProcessDirectiveInterface;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\Catalog\Model\Product;

/**
 * ProcessUpdateAvailabilityDirective class
 */
class ProcessUpdateAvailabilityDirective implements ProcessDirectiveInterface
{
    const ORDER_ID_KEY = 'order_id';

    /**
     * @var OneOGraphQLClient
     */
    private $graphQLClient;

    /**
     * @var StockStatusRepositoryInterface
     */
    private $stockStatusRepository;

    /**
     * @var Product
     */
    private $productModel;

    /**
     * @param OneOGraphQLClient $graphQLClient
     * @param StockStatusRepositoryInterface $stockStatusRepository
     * @param Product $productModel
     */
    public function __construct(
        OneOGraphQLClient $graphQLClient,
        StockStatusRepositoryInterface $stockStatusRepository,
        Product $productModel
    ) {
        $this->graphQLClient = $graphQLClient;
        $this->stockStatusRepository = $stockStatusRepository;
        $this->productModel = $productModel;
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
            $magentoProductSku = $oneOLineItem["variantExternalId"] ?? $oneOLineItem["productExternalId"];
            $magentoProductId = $this->productModel->getIdBySku($magentoProductSku);
            $stockStatus = $this->stockStatusRepository->get($magentoProductId);

            $productStockStatus = (int)$stockStatus->getStockStatus();

            $itemAvailabilities[] = [
                "id" => $oneOLineItem["id"],
                "available" => $productStockStatus === StockStatusInterface::STATUS_IN_STOCK,
            ];
        }

        $graphQlClient = $this->graphQLClient->getClient();
        $graphQlClient->updateAvailabilities($orderId, $itemAvailabilities);

        return ['status' => 'ok'];
    }
}
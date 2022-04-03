<?php

declare(strict_types=1);

namespace OneO\Shop\Model;

use OneO\Shop\Api\Data\ProcessDirectiveInterface;

class ProcessImportProductDirective implements ProcessDirectiveInterface
{
    const PRODUCT_URL_KEY = 'product_url';
    private \Magento\Catalog\Api\ProductRepositoryInterface $productRepository;
    private \OneO\Shop\Helper\ProductMapperFactory $productMapper;
    private OneOGraphQLClient $graphQLClient;

    /**
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \OneO\Model\PasetoToken $pasetoToken
     * @param \OneO\Shop\Helper\ProductMapperFactory $productMapper
     * @param OneOGraphQLClient $graphQLClient
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \OneO\Shop\Helper\ProductMapperFactory $productMapper,
        \OneO\Shop\Model\OneOGraphQLClient $graphQLClient
    )
    {
        $this->productRepository = $productRepository;
        $this->productMapper = $productMapper;
        $this->graphQLClient = $graphQLClient;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): string
    {
        $arguments = $jsonDirective[self::ARGS_KEY];
        $productUrl = $arguments[self::PRODUCT_URL_KEY];
        $productSku = $this->parseProductIdFromUrl($productUrl);

        $product = $this->productRepository->get($productSku);
        $productMapper = $this->productMapper->create();
        $mappedProduct = $productMapper->mapMagentoProductTo1oProduct($product);

        $graphQlClient = $this->graphQLClient->getClient();
        $result = $graphQlClient->createProduct($mappedProduct);

        return 'ok';
    }

    private function parseProductIdFromUrl($productUrl)
    {
        $matches = [];
        preg_match("/\/([^\/]+)\/?$/", $productUrl, $matches);
        return $matches[1];
    }
}
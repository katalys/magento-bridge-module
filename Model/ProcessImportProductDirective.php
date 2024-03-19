<?php

declare(strict_types=1);

namespace Katalys\Shop\Model;

use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewriteGraphQl\Model\Resolver\UrlRewrite\CustomUrlLocatorInterface;
use Katalys\Shop\Api\Data\ProcessDirectiveInterface;

class ProcessImportProductDirective implements ProcessDirectiveInterface
{
    const PRODUCT_URL_KEY = 'product_url';
    private \Magento\Catalog\Api\ProductRepositoryInterface $productRepository;
    private \Katalys\Shop\Helper\ProductMapperFactory $productMapper;
    private OneOGraphQLClient $graphQLClient;
    private UrlFinderInterface $urlFinder;
    private CustomUrlLocatorInterface $customUrlLocator;
    private \Magento\Store\Model\StoreManagerInterface $storeManager;

    /**
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Katalys\Shop\Helper\ProductMapperFactory $productMapper
     * @param OneOGraphQLClient $graphQLClient
     * @param UrlFinderInterface $urlFinder
     * @param CustomUrlLocatorInterface $customUrlLocator
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Katalys\Shop\Helper\ProductMapperFactory $productMapper,
        \Katalys\Shop\Model\OneOGraphQLClient $graphQLClient,
        UrlFinderInterface $urlFinder,
        CustomUrlLocatorInterface $customUrlLocator,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->productRepository = $productRepository;
        $this->productMapper = $productMapper;
        $this->graphQLClient = $graphQLClient;
        $this->urlFinder = $urlFinder;
        $this->customUrlLocator = $customUrlLocator;
        $this->storeManager = $storeManager;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): array
    {
        $arguments = $jsonDirective[self::ARGS_KEY];
        $productUrl = $arguments[self::PRODUCT_URL_KEY];
        $productId = $this->parseProductIdFromUrl($productUrl);

        $product = $this->productRepository->getById($productId);
        $productMapper = $this->productMapper->create();
        $mappedProduct = $productMapper->mapMagentoProductTo1oProduct($product);

        $graphQlClient = $this->graphQLClient->getClient();
        $result = $graphQlClient->createProduct($mappedProduct);

        return ['status' => 'ok', 'result' => $result["data"]["createProduct"]["id"] ?? ''];
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function parseProductIdFromUrl($productUrl)
    {
        $urlParts = parse_url($productUrl);
        $url = $urlParts['path'] ?? $productUrl;
        if (substr($url, 0, 1) === '/' && $url !== '/') {
            $url = ltrim($url, '/');
        }

        $customUrl = $this->customUrlLocator->locateUrl($url);
        $url = $customUrl ?: $url;
        $storeId = intval($this->storeManager->getStore()->getId());
        $finalUrlRewrite = $this->findFinalUrl($url, $storeId);

        if ($finalUrlRewrite && $finalUrlRewrite->getEntityType() === 'product') {
            return $finalUrlRewrite->getEntityId();
        } else {
            // Url still not found, try to parse admin url pattern
            $matches = [];
            preg_match("/catalog\/product\/edit\/id\/(\d+)/", $productUrl, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Find the final url passing through all redirects if any
     *
     * @param string $requestPath
     * @param int $storeId
     * @param bool $findCustom
     * @return UrlRewrite|null
     */
    private function findFinalUrl(string $requestPath, int $storeId, bool $findCustom = false): ?UrlRewrite
    {
        $urlRewrite = $this->findUrlFromRequestPath($requestPath, $storeId);
        if ($urlRewrite) {
            $this->redirectType = $urlRewrite->getRedirectType();
            while ($urlRewrite && $urlRewrite->getRedirectType() > 0) {
                $urlRewrite = $this->findUrlFromRequestPath($urlRewrite->getTargetPath(), $storeId);
            }
        } else {
            $urlRewrite = $this->findUrlFromTargetPath($requestPath, $storeId);
        }
        if ($urlRewrite && ($findCustom && !$urlRewrite->getEntityId() && !$urlRewrite->getIsAutogenerated())) {
            $urlRewrite = $this->findUrlFromTargetPath($urlRewrite->getTargetPath(), $storeId);
        }

        return $urlRewrite;
    }

    /**
     * Find a url from a request url on the current store
     *
     * @param string $requestPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    private function findUrlFromRequestPath(string $requestPath, int $storeId): ?UrlRewrite
    {
        return $this->urlFinder->findOneByData(
            [
                'request_path' => $requestPath,
                'store_id' => $storeId
            ]
        );
    }

    /**
     * Find a url from a target url on the current store
     *
     * @param string $targetPath
     * @param int $storeId
     * @return UrlRewrite|null
     */
    private function findUrlFromTargetPath(string $targetPath, int $storeId): ?UrlRewrite
    {
        return $this->urlFinder->findOneByData(
            [
                'target_path' => $targetPath,
                'store_id' => $storeId
            ]
        );
    }
}
<?php

namespace Katalys\Shop\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Katalys\Shop\Api\Data\ProcessDirectiveInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ProcessProductInformationSyncDirective
 */
class ProcessProductInformationSyncDirective implements ProcessDirectiveInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * @param $jsonDirective
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): array
    {
        $arguments = $jsonDirective[self::ARGS_KEY];
        if (!$arguments['product_ids']) {
            return [];
        }

        $response = [];
        foreach ($arguments['product_ids'] as $productId) {
            $result = [];
            if (!isset($productId['external_id'])) {
                continue;
            }

            $product = $this->productRepository->getById($productId['external_id']);
            $result['id'] = $productId['id'];
            if (!isset($productId['variants']) || !$productId['variants']) {
                $result['price'] = $product->getFinalPrice() * 100;
                $result['compare_at_price'] = ($product->getFinalPrice() < $product->getPrice()) ? $product->getPrice() * 100 : 0;
                $result['currency'] = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
                $response['data'][] = $result;
                continue;
            }
            foreach ($productId['variants'] as $variant) {
                $variantId = $variant['external_id'];
                $variantObject = $this->productRepository->getById($variantId);
                $result['variants'][] = [
                    'id' => $variant['id'],
                    'price' => $variantObject->getFinalPrice() * 100,
                    "currency" => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                    'compare_at_price' => ($variantObject->getFinalPrice() < $variantObject->getPrice()) ? $variantObject->getPrice() * 100 : 0
                ];
            }
            $response['data'][] = $result;
        }
        return $response;
    }
}

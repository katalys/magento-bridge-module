<?php

namespace Katalys\Shop\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * ProductMapper class
 */
class ProductMapper
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array
     */
    private $positionToMagentoId = [];

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager) {
        $this->storeManager = $storeManager;
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    public function mapMagentoProductTo1oProduct(ProductInterface $product)
    {
        $images = [];

        foreach ($product->getMediaGalleryImages()->getItems() as $image) {
            $images[] = $image->getUrl();
        }

        $mappedProduct = [
            "name" => $product->getName(),
            "title" => $product->getName(),
            "currency" => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            "currency_sign" => $this->storeManager->getStore()->getCurrentCurrency()->getCurrencySymbol(),
            "price" => $product->getFinalPrice() * 100,
            "compare_at_price" => ($product->getFinalPrice() < $product->getPrice()) ? $product->getPrice() * 100 : 0,
            "summary_md" => "",
            "summary_html" => $product->getShortDescription(),
            "details_html" => $product->getDescription(),
            "external_id" => $product->getId(),
            "shop_url" => $product->getProductUrl(),
            "images" => $images,
        ];

        if ($product->getTypeId() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            $options = $product->getTypeInstance()->getConfigurableOptions($product);
            $usedProducts = $product->getTypeInstance()->getUsedProducts($product);

            $optionsNames = $this->mapMagentoOptionsTo1oOptions($options);
            $mappedProduct["variant"] = false;
            $mappedProduct["variants"] = $this->mapMagentoOptionsTo1oVariants($usedProducts, $options, $optionsNames);
            $mappedProduct["option_names"] = $optionsNames;
        }

        return $mappedProduct;
    }

    public function mapMagentoOptionsTo1oVariants($usedProducts, $options, $optionNames) {
        $oneOVariants = [];
        if ($usedProducts) {
            foreach ($usedProducts as $usedProduct) {
                $oneOVariant = $this->mapMagentoProductTo1oProduct($usedProduct);

                $oneOVariant["subtitle"] = $oneOVariant["title"];
                $oneOVariant["variant"] = true;

                foreach ($optionNames as $optionName) {
                    $pos = $optionName["position"];
                    $name = $optionName["name"];
                    $magentoOptionId = $this->positionToMagentoId[$pos];
                    $sku = $usedProduct->getSku();
                    $index = array_search($sku, array_column($options[$magentoOptionId], "sku"));
                    $value = $options[$magentoOptionId][$index]["option_title"];
                    $pathKey = "option_" . $pos . "_names_path";
                    $value = [
                        $name,
                        $value
                    ];

                    $oneOVariant[$pathKey] = $value;
                }

                $oneOVariants[] = $oneOVariant;
            }
        }
        return $oneOVariants;
    }

    /**
     * @param $options
     * @return array
     */
    public function mapMagentoOptionsTo1oOptions($options) {
        $oneOOptions = [];
        if ($options) {
            $pos = 0;
            foreach ($options as $optionId => $optionSelections) {
                $optionLabel = reset($optionSelections)["super_attribute_label"];
                $optionPosition = ++$pos;
                $oneOOptionSelection = [];
                $optPos = 0;
                foreach ($optionSelections as $optionSelection) {
                    // Skip duplicates
                    if (in_array($optionSelection["option_title"], array_column($oneOOptionSelection, "name"))) {
                        continue;
                    }

                    $oneOOptionSelection[] = [
                        "name" => $optionSelection["option_title"],
                        "position" => ++$optPos
                    ];
                }

                $this->positionToMagentoId[$pos] = $optionId;

                $oneOOptions[] = [
                    "name" => $optionLabel,
                    "position" => $optionPosition,
                    "options" => $oneOOptionSelection
                ];
            }
        }

        return $oneOOptions;
    }
}
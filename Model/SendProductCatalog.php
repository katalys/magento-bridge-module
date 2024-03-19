<?php

namespace Katalys\Shop\Model;

use Magento\Framework\Exception\SecurityViolationException;
use Katalys\Shop\Api\SendProductCatalogInterface;
use Katalys\Shop\Util\Sec\Authenticatable;
use Katalys\Shop\Util\Curl;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * SendProductCatalog class
 */
class SendProductCatalog implements SendProductCatalogInterface
{
    use Authenticatable;

    const DEFAULT_TIMEOUT = 55;
    const DEFAULT_LIMIT = 200;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     */
    protected $configurable;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlInterface;

    /**
     * @var \Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory
     */
    protected $rulesCollectionFactory;

    /**
     * @var array
     */
    private $visibilities;

    /**
     * @var array
     */
    private $statuses;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var array|false|int|string|null
     */
    private $host;

    /**
     * @var array
     */
    private $rules;

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable
     * @param \Magento\Framework\UrlInterface $urlInterface
     * @param \Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Psr\Log\LoggerInterface $logger,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory
    ) {
        $this->request = $request;
        $this->collectionFactory = $collectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->configurable = $configurable;
        $this->urlInterface = $urlInterface;
        $this->rulesCollectionFactory = $ruleCollectionFactory;
        $this->visibilities = Visibility::getOptionArray();
        $this->statuses = Status::getOptionArray();
        $this->baseUrl = $this->urlInterface->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $this->host = parse_url($this->baseUrl, PHP_URL_HOST);
        $this->_initRules();
    }

    /**
     * @return array|void
     * @throws SecurityViolationException
     */
    public function send()
    {
        $this->authenticate($this->request);
        $params = $this->request->getParams();
        $limit = $params['limit'] ?? self::DEFAULT_LIMIT;
        $offset = $params['offset'] ?? null;
        $timeout = $params['timeout'] ?? self::DEFAULT_TIMEOUT;
        $send = $params['send'] ?? null;
        $skipsales = $params['skipsales'] ?? false;
        $startTime = microtime(true);
        $fp = fopen('php://temp', 'w+');
        $i = 0;
        $breakFlag = false;
        do {
            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToSelect('*');
            $collection->setOrder(
                'entity_id',
                'desc'
            );
            $collection->getSelect()->limit($limit, $offset);

            foreach ($collection->getItems() as $product) {
                $i++;
                fwrite($fp, json_encode($this->_getProduct($product, $skipsales), JSON_UNESCAPED_SLASHES));
                fwrite($fp, "\n");

                if (($startTime + $timeout) < microtime(true)) {
                    $breakFlag = true;
                    fwrite($fp, "TIMED OUT totalRecords=$i\n");
                    break;
                }
            }
            $offset += $limit;
        } while (!$breakFlag && count($collection) >= $limit);

        // prep temp file for upload
        $fileSize = ftell($fp);
        rewind($fp);

        if ($send) {
            $ch = curl_init(Curl::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_INFILE => $fp,
                CURLOPT_INFILESIZE => $fileSize,
                CURLOPT_USERAGENT => Curl::getUserAgent(),
                CURLOPT_VERBOSE => true,
            ]);
            curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            return [
                [
                    'success' => true,
                    'timeout' => $breakFlag,
                    'sent' => $info
                ]
            ];
        } else {
            return [
                [
                    'success' => true,
                    'data' => stream_get_contents($fp),
                ]
            ];
        }
    }

    /**
     * Initializes the rules cache used to find discount prices
     */
    private function _initRules()
    {
        /** @var \Magento\CatalogRule\Model\ResourceModel\Rule\Collection $collection */
        $collection = $this->rulesCollectionFactory->create();
        $collection->addCustomerGroupFilter(0); // 0 = NOT LOGGED IN
        $this->rules = [];
        foreach ($collection->getItems() as $rule) {
            $this->rules[] = $rule;
        }
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    private function _getProduct($product, $skipsales = false)
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'type' => $product->getTypeId(),
            'status' => $this->statuses[$product->getStatus()] ?? null,
            'description' => $product->getDescription(),
            'sku' => $product->getSku(),
            'parent_id' => $this->configurable->getParentIdsByChild($product->getId()),
            'date_created' => $product->getCreatedAt(),
            'date_modified' => $product->getUpdatedAt(),
            'catalog_visibility' => $this->visibilities[$product->getVisibility()] ?? null,
            'price' => $product->getPrice(),
            'sale_price_rules' => $skipsales ? null : $this->_getSalesPrices($product),
            'weight' => $product->getWeight(),
            'permalink' => ($product->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE) ? null :
                $this->baseUrl . $product->getUrlKey() . '.html',
            'image' => 'http://' . $this->host . '/pub/media/catalog/product/' . $product->getImage()
        ];
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    private function _getSalesPrices($product)
    {
        $sales = [];
        foreach ($this->rules as $rule) {
            $salePrice = $rule->calcProductPriceRule($product, $product->getPrice());
            if ($salePrice) {
                // make more readable object to be JSON encoded
                $sales[] = [
                    'id' => $rule->getId(),
                    'name' => $rule->getName(),
                    'price' => $salePrice,
                ];
            }
        }

        return $sales;
    }
}
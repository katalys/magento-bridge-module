<?php

namespace Katalys\Shop\Model;

use Magento\Framework\Exception\SecurityViolationException;
use Katalys\Shop\Api\SendCatalogRulesInterface;
use Katalys\Shop\Util\Sec\Authenticatable;

/**
 * SendCatalogRules class
 */
class SendCatalogRules implements SendCatalogRulesInterface
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
     * @var \Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory
     */
    protected $rulesCollectionFactory;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory
    ) {
        $this->logger = $logger;
        $this->request = $request;
        $this->rulesCollectionFactory = $ruleCollectionFactory;
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
        $startTime = microtime(true);
        $fp = fopen('php://temp', 'w+');
        $i = 0;
        $breakFlag = false;
        do {
            /** @var \Magento\CatalogRule\Model\ResourceModel\Rule\Collection $collection */
            $collection = $this->rulesCollectionFactory->create();
            $collection->setOrder(
                'rule_id',
                'desc'
            );
            $collection->getSelect()->limit($limit, $offset);

            foreach ($collection->getItems() as $rule) {
                $i++;
                fwrite($fp, json_encode($rule->get(), JSON_UNESCAPED_SLASHES));
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
        return [
            [
                'success' => true,
                'data' => stream_get_contents($fp),
            ]
        ];
    }

}
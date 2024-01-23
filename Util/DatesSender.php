<?php

namespace OneO\Shop\Util;

/**
 * DatesSender class
 */
class DatesSender
{
    const MIN_TIME = '-1 year';
    const DEFAULT_LIMIT = 200;
    const DEFAULT_TIMEOUT = 55;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \OneO\Shop\Util\OrderPackagerFactory
     */
    protected $orderPackagerFactory;

    /**
     * @var \OneO\Shop\Model\QueueEntryFactory
     */
    protected $queueEntryFactory;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param OrderPackagerFactory $orderPackagerFactory
     * @param \OneO\Shop\Model\QueueEntryFactory $queueEntryFactory
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \OneO\Shop\Util\OrderPackagerFactory $orderPackagerFactory,
        \OneO\Shop\Model\QueueEntryFactory $queueEntryFactory
    ) {
        $this->logger = $logger;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderPackagerFactory = $orderPackagerFactory;
        $this->queueEntryFactory = $queueEntryFactory;
    }

    /**
     * Send orders within date range to collection endpoint
     *
     * @param $fromDate
     * @param $toDate
     * @param int $limit
     * @param int $offset
     * @param int $timeout
     * @return array
     * @throws \Exception
     */
    public function send(
        $fromDate,
        $toDate,
        $limit = self::DEFAULT_LIMIT,
        $offset = 0,
        $timeout = self::DEFAULT_TIMEOUT
    ) {
        if (!$this->validDates($fromDate, $toDate)) {
            throw new \Exception('invalid date range.');
        }

        $done = 0;
        $startTime = microtime(true);
        $breakFlag = false;
        $map = [];

        $orderPackager = $this->orderPackagerFactory->create();
        $iterator = $this->iterateOrdersByDate($fromDate, $toDate, $limit, $offset, $breakFlag);
        foreach ($iterator as $order) {
            $id = $order->getId();
            $this->logger->debug(__METHOD__ . ': ' . $order->getId());
            $map[$id] = '?';
            $params = $orderPackager->getParams($id, true);
            $params['action'] = 'restapi_conv';
            $req = \OneO\Shop\Util\Curl::post($params);
            $req->callback = function ($out, $info) use ($id, &$map, &$done) {
                $done++;
                $map[$id] = in_array($info['http_code'], [200, 204]);
            };
        }

        $rollingCurlInstance = \OneO\Shop\Util\Curl::getDefault();
        while ($rollingCurlInstance->tick()) {
            if ($timeout > 0 && $startTime + $timeout < microtime(true)) {
                $breakFlag = true;
                break;
            }
        }

        return [
            'success' => true,
            'sent' => $done,
            'timeout' => $breakFlag,
            'map' => $map,
        ];
    }

    /**
     * Queue orders within date range
     *
     * @param $fromDate
     * @param $toDate
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws \Exception
     */
    public function queue(
        $fromDate,
        $toDate,
        $limit = self::DEFAULT_LIMIT,
        $offset = 0
    ) {
        if (!$this->validDates($fromDate, $toDate)) {
            throw new \Exception('invalid date range.');
        }
        $breakFlag = false;
        $iterator = $this->iterateOrdersByDate($fromDate, $toDate, $limit, $offset, $breakFlag, false);
        $i = 0;
        $ids = [];
        foreach ($iterator as $order) {
            $i++;
            $orderId = $order->getId();
            $ids[] = $orderId;
            /** @var \OneO\Shop\Model\QueueEntry $entry */
            $entry = $this->queueEntryFactory->create();
            $entry->setData('order_id', $orderId);
            $entry->save();
        }

        return [
            'queued' => $i,
            'ids' => $ids,
        ];
    }

    /**
     * Iterate orders within date range with limit and offset
     *
     * @param $fromDate
     * @param $toDate
     * @param $limit
     * @param $offset
     * @param $breakFlag
     * @param boolean $getMore determines whether iterator should attempt to get more beyond the limit (batch size)
     * @return \Generator|int
     */
    protected function iterateOrdersByDate(
        $fromDate,
        $toDate,
        $limit,
        $offset,
        &$breakFlag,
        $getMore = true
    ) {
        $createdAtCondition = [
            'from' => date("Y-m-d h:i:s", strtotime($fromDate))
        ];
        if ($toDate) {
            $createdAtCondition['to'] = date("Y-m-d h:i:s", strtotime($toDate));
        }

        do {
            $collection = $this->orderCollectionFactory->create()
                ->addAttributeToSelect('entity_id')
                ->addFieldToFilter(
                    'created_at',
                    $createdAtCondition
                )
                ->setOrder(
                    'created_at',
                    'desc'
                );
            $collection->getSelect()->limit($limit, $offset);
            $offset += $limit;

            $i = 0;
            foreach ($collection as $item) {
                $i++;
                yield $item;
                if ($breakFlag) {
                    return $collection->getSize();
                }
            }
        } while ($getMore && ($i >= $limit));

        return $collection->getSize();
    }

    /**
     * @param $fromDate
     * @param $toDate
     * @return bool
     */
    protected function validDates($fromDate, $toDate)
    {
        $minTime = strtotime(self::MIN_TIME);
        $dateFrom = strtotime($fromDate);
        $dateTo = strtotime($toDate);

        if ($dateFrom < $minTime || $dateTo < $minTime || $dateFrom > $dateTo) {
            return false;
        }

        return true;
    }
}
<?php

namespace OneO\Shop\Cron;

use Psr\Log\LoggerInterface;
use OneO\Shop\Model\ResourceModel\QueueEntry\CollectionFactory;
use OneO\Shop\Util\OrderPackagerFactory;

/**
 * CollectorRunner class
 */
class CollectorRunner
{
    const BATCH_SIZE = 100;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var OrderPackagerFactory
     */
    protected $orderPackagerFactory;

    /**
     * @param LoggerInterface $logger
     * @param CollectionFactory $collectionFactory
     * @param OrderPackagerFactory $orderPackagerFactory
     */
    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        OrderPackagerFactory $orderPackagerFactory
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->orderPackagerFactory = $orderPackagerFactory;
    }

    /**
     * Default method to execute cron
     * @return void
     */
    public function execute()
    {
        $collection = $this->collectionFactory->create();
        $collection->getSelect()
            ->order('_id DESC')->limit(self::BATCH_SIZE);

        $orderPackager = $this->orderPackagerFactory->create();
        foreach($collection as $entry) {
            $orderId = $entry->getData('order_id');
            $params = $orderPackager->getParams($orderId, true);
            if ($params) {
                $params['action'] = 'offline_conv';
                $res = \OneO\Shop\Util\Curl::post($params);
                if ($res) {
                    $res->callback = function($out, $info) use ($entry) {
                        if (!in_array($info['http_code'], [200,204])) {
                            $this->logger->warning(__METHOD__ . ': unable to send order id=' . $entry->getData('order_id'));
                            return;
                        }
                        $entry->delete();
                    };
                }
            } else {
                $this->logger->error(__METHOD__ . ": unable to get params for order id=$orderId ");
            }
        }
    }
}

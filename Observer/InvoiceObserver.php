<?php

namespace Katalys\Shop\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Katalys\Shop\Model\QueueEntryFactory;
use Katalys\Shop\Helper\Data;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Katalys\Shop\Api\ConfigInterface;

/**
 * InvoiceObserver class
 */
class InvoiceObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QueueEntryFactory
     */
    protected $queueEntryFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @param LoggerInterface $logger
     * @param QueueEntryFactory $queueEntryFactory
     * @param Data $helper
     * @param ConfigInterface $config
     */
    public function __construct(
        LoggerInterface $logger,
        QueueEntryFactory $queueEntryFactory,
        Data $helper,
        ConfigInterface $config
    ) {
        $this->logger = $logger;
        $this->queueEntryFactory = $queueEntryFactory;
        $this->helper = $helper;
        $this->config = $config;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();
            $trigger = $this->helper->getTrigger();
            $orderState = $order->getState();

            if ($this->config->isDebugMode()) {
                $this->logger->debug("trigger=$trigger, ostate=$orderState");
            }

            /**
             * Note: there is a difference between Status and State
             * assumption is that orders do not skip the "new" State
             */
            if ((!$trigger && $orderState == \Magento\Sales\Model\Order::STATE_NEW) ||
                ($trigger == $orderState) ||
                ($orderState == Order::STATE_CANCELED) ||
                ($orderState == Order::STATE_CLOSED) ||
                $this->config->isTriggerAllStatus()
            ) { // CLOSED are expected as refunds

                /**
                 * When Orders are sent to Katalys backend the Status is sent not State
                 *  Order Statuses of: closed, canceled, or fraud should be considered rejections
                 */

                if ($this->helper->useCron()) {
                    $this->useCron($order);
                } else {
                    $this->callApi($order);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ': ' . $e->getMessage());
        }
    }

    /**
     * @param Order $order
     * @return void
     */
    protected function useCron(Order $order)
    {
        /** @var \Katalys\Shop\Model\QueueEntry $model */
        $model = $this->queueEntryFactory->create();
        $model->addData([
            'order_id' => $order->getId()
        ]);

        try {
            $res = $model->save();
            if ($res) {
                if ($this->config->isDebugMode()) {
                    $this->logger->info(__METHOD__ . ': saved order id=' . $order->getId() . ' to queue table.');
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ': ' . $e->getMessage());
        }
    }

    /**
     * @param Order $order
     * @return void
     */
    protected function callApi(Order $order)
    {
        $params = \Katalys\Shop\Util\OrderPackager::_mapData($order);
        if ($params) {
            $params['action'] = 'offline_conv';
            if ($this->config->isDebugMode()) {
                $this->logger->debug(__CLASS__ . ': params=' . \json_encode($params));
            }
            \Katalys\Shop\Util\Curl::post($params);
        } else {
            $this->logger->error(__METHOD__ . ": unable to record order id= " . $order->getId());
        }
    }
}

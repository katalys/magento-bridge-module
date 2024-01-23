<?php

namespace OneO\Shop\Util;

/**
 * OrderStatusUpdater class
 */
class OrderStatusUpdater
{
    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * @param $orderId
     * @param $note
     * @param $isKey
     * @return bool
     */
    public function addOrderNote($orderId, $note, $isKey = false)
    {
        if (!$orderId) {
            return false;
        }

        try {
            if ($isKey) {
                $order = $this->orderRepository->get($orderId);
            } else {
                $this->searchCriteriaBuilder->addFilter('increment_id', $orderId);
                $orders = $this->orderRepository->getList(
                    $this->searchCriteriaBuilder->create()
                )->getItems();
                if (is_array($orders) && count($orders) === 1) {
                    $order = array_shift($orders);
                } else {
                    return false;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ':' . $e->getMessage());
            return false;
        }

        if (!$order) {
            return false;
        }

        $order->addCommentToStatusHistory($note);
        $order->save();

        return true;
    }
}
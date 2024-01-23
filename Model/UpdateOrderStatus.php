<?php

namespace OneO\Shop\Model;

use OneO\Shop\Api\UpdateOrderStatusInterface;
use OneO\Shop\Util\Sec\Authenticatable;
use Magento\Framework\Exception\SecurityViolationException;

/**
 * UpdateOrderStatus class
 */
class UpdateOrderStatus implements UpdateOrderStatusInterface
{
    use Authenticatable;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \OneO\Shop\Util\OrderStatusUpdaterFactory
     */
    protected $orderStatusUpdaterFactory;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * UpdateOrderStatus constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \OneO\Shop\Util\OrderStatusUpdaterFactory $orderStatusUpdaterFactory
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \OneO\Shop\Util\OrderStatusUpdaterFactory $orderStatusUpdaterFactory,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->logger = $logger;
        $this->orderStatusUpdaterFactory = $orderStatusUpdaterFactory;
        $this->request = $request;
    }

    /**
     * @param string $id
     * @return array
     * @throws SecurityViolationException
     */
    public function update($id)
    {
        $this->authenticate($this->request);
        $params = $this->request->getParams();
        $isConverted = $params['conversion_status'] ?? null;
        $conversionMessage = $params['conversion_message'] ?? null;
        $key = $params['key'] ?? null;
        if (!$conversionMessage) {
            $conversionMessage = "Katalys Advertiser status change: $isConverted";
        }
        /** @var \OneO\Shop\Util\OrderStatusUpdater $orderStatusUpdater */
        $orderStatusUpdater = $this->orderStatusUpdaterFactory->create();
        $success = $orderStatusUpdater->addOrderNote($id, $conversionMessage, $key);
        return [
            [
                'success' => $success
            ]
        ];
    }
}
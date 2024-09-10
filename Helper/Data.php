<?php

namespace Katalys\Shop\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Model\ResourceModel\Order as ResourceModelOrder;
/**
 * Data class
 */
class Data extends AbstractHelper
{
    const SITEID_CONFIG_PATH = 'katalys_ad/configs/siteid';
    const USE_CRON_CONFIG_PATH = 'katalys_ad/configs/usecron';
    const TRIGGER = 'katalys_ad/configs/trigger';
    const INCLUDE_JS = 'katalys_ad/configs/includejs';

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var ResourceModelOrder
     */
    protected $resourceModelOrder;

    /**
     * @var OrderInterfaceFactory
     */
    protected $modelOrder;

    /**
     * @var OrderInterface
     */
    protected $order;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param ResourceModelOrder $resourceModelOrder
     * @param OrderInterfaceFactory $modelOrder
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        ResourceModelOrder $resourceModelOrder,
        OrderInterfaceFactory $modelOrder
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->resourceModelOrder = $resourceModelOrder;
        $this->modelOrder = $modelOrder;
    }

    /**
     * @return mixed
     */
    public function getSiteId()
    {
        return $this->getConfig(self::SITEID_CONFIG_PATH);
    }

    /**
     * @return mixed
     */
    public function useCron()
    {
        return $this->getConfig(self::USE_CRON_CONFIG_PATH);
    }

    /**
     * @return mixed
     */
    public function getTrigger()
    {
        return $this->getConfig(self::TRIGGER);
    }

    /**
     * @return bool
     */
    public function getIncludeJs()
    {
        $val = $this->getConfig(self::INCLUDE_JS);
        return $val === null || $val === '' || $val;
    }

    /**
     * @param $config_path
     * @return mixed
     */
    protected function getConfig($config_path)
    {
        return $this->scopeConfig->getValue($config_path);
    }

    /**
     * @return mixed
     */
    public function getOrderId()
    {
        if ($this->orderId) {
            return $this->orderId;
        }
        return $this->checkoutSession->getLastRealOrderId();
    }

    /**
     * @param $orderId
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @return OrderInterface|null
     */
    public function getOrder()
    {
        if ($this->order) {
            return $this->order;
        }

        if (!$this->getOrderId()) {
            return null;
        }

        $this->order = $this->modelOrder->create();
        $this->resourceModelOrder->load($this->order, $this->getOrderId(), 'increment_id');
        return $this->order;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        $orderStatus = $this->getOrder()->getStatus();
        if ($orderStatus == "fraud") {
            return "rejected";
        } elseif ($orderStatus == "canceled") {
            return "cancelled";
        } elseif ($orderStatus == "closed") {
            return "refunded";
        } elseif ($orderStatus === 'complete') {
            return "fulfilled";
        } elseif ($orderStatus === 'processing') {
            return "pending";
        } else {
            return $orderStatus;
        }
    }

    /**
     * @return string
     */
    public function getOrderTime()
    {
        return $this->getOrder()->getCreatedAt();
    }
}
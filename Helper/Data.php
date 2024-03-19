<?php

namespace Katalys\Shop\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;

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
    private $checkoutSession;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
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
        return $this->checkoutSession->getLastRealOrderId();
    }
}
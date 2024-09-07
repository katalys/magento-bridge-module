<?php

namespace Katalys\Shop\Model;

use Katalys\Shop\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Config class
 */
class Config implements ConfigInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @return int|null
     */
    protected function getStoreId(): ?int
    {
        try {
            return $this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::_KEY_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        ) ?? false;
    }

    /**
     * @return bool
     */
    public function isTriggerAllStatus(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::_KEY_TRIGGER_ALL_STATUS,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        ) ?? false;
    }
}
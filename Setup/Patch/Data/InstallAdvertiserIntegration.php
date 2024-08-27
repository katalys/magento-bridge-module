<?php

namespace Katalys\Shop\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Katalys\Shop\Helper\Data;
use Katalys\Shop\Util\Curl;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class InstallAdvertiserIntegration
 */
class InstallAdvertiserIntegration implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param UrlInterface $urlInterface
     * @param LoggerInterface $logger
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        UrlInterface $urlInterface,
        LoggerInterface $logger,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->urlInterface = $urlInterface;
        $this->logger = $logger;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $siteId = $this->getSiteId();
            if ($siteId) {
                $this->configWriter->save(Data::SITEID_CONFIG_PATH, $siteId);
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ': error getting siteId.', [ $e ]);
        }

        try {
            $this->configWriter->save(Data::USE_CRON_CONFIG_PATH, $this->getUseCronConfig());
            $trigger = $this->scopeConfig->getValue('revoffers/configs/trigger');
            if ($trigger) {
                $this->configWriter->save(Data::TRIGGER, $trigger);
            }
            $includeJs = $this->scopeConfig->getValue('revoffers/configs/includejs');
            if ($includeJs) {
                $this->configWriter->save(Data::INCLUDE_JS, $trigger);
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ': error setting use cron config.', [ $e ]);
        }
        Curl::sendNotification('install');
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @return bool
     */
    protected function getUseCronConfig(): bool
    {
        if ($this->scopeConfig->isSetFlag('revoffers/configs/usecron') === null) {
            return true;
        }
        return $this->scopeConfig->isSetFlag('revoffers/configs/usecron');
    }

    /**
     * Generate the default siteId string based on the website's URL.
     *
     * @return string|null
     */
    protected function getSiteId() {
        $siteId = $this->scopeConfig->getValue('revoffers/configs/siteid');
        if ($siteId) {
            return $siteId;
        }

        $baseUrl = $this->urlInterface->getBaseUrl();
        $siteHost = parse_url($baseUrl, PHP_URL_HOST);
        if ($siteHost) {
            return $this->sanitize($siteHost);
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            return $this->sanitize($_SERVER['HTTP_HOST']);
        }
        return null;
    }

    /**
     * @param string $value
     * @return array|string|string[]|null
     */
    protected function sanitize(string $value) {
        $value = trim($value);
        if (strpos($value, "://")) {
            $value = parse_url($value, PHP_URL_HOST) ?: $value;
        }
        $value = preg_replace('#:\d*$#', '', $value);
        return preg_replace('#^(?:store|shop|www)\.(\w.*\.\w+)\.?$#i', '$1', $value);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}

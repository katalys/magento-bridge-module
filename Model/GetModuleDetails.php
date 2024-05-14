<?php

namespace Katalys\Shop\Model;

use Katalys\Shop\Api\GetModuleDetailsInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Class GetModuleDetails
 */
class GetModuleDetails implements GetModuleDetailsInterface
{
    /**
     * @var Curl
     */
    protected $client;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var string
     */
    protected $dataApiVersion;

    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * @const string
     */
    const URL_API = 'https://repo.packagist.org/p2/katalys/magento-bridge-module.json';
    const MODULE_NAME = 'katalys/magento-bridge-module';

    /**
     * @param Curl $client
     * @param SerializerInterface $serializer
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        Curl $client,
        SerializerInterface $serializer,
        ModuleListInterface $moduleList
    ) {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->dataApiVersion = null;
        $this->moduleList = $moduleList;
    }

    /**
     * @return string
     */
    public function getNewVersion(): string
    {
        return $this->getDataInApi() ?? $this->getActualVersion();
    }

    /**
     * @return string
     */
    public function getActualVersion(): string
    {
        return $this->moduleList->getOne('Katalys_Shop')['setup_version'];
    }

    /**
     * @return bool
     */
    public function hasNewVersion(): bool
    {
        return $this->getNewVersion() > $this->getActualVersion();
    }

    /**
     * @return bool|string
     */
    protected function getDataInApi()
    {
        if ($this->dataApiVersion) {
            return $this->dataApiVersion;
        }
        $this->client->get(self::URL_API, []);
        try {
            $body = $this->serializer->unserialize($this->client->getBody());
        } catch (\Exception $e) {
            return false;
        }

        if (empty($body)) {
            return false;
        }

        if (!isset($body['packages'])) {
            return false;
        }

        $packages = $body['packages'];
        if (!isset($packages[self::MODULE_NAME])) {
            return false;
        }
        $details = $packages[self::MODULE_NAME];
        if (!isset($details[0]['version'])) {
            return false;
        }
        $this->dataApiVersion = $details[0]['version'];
        return $this->dataApiVersion;
    }
}

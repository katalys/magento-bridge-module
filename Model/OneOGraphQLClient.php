<?php

namespace Katalys\Shop\Model;

use Katalys\Model\GraphQLFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Katalys\Model\PasetoToken;
use Katalys\Model\KatalysToken;
use Magento\Framework\Encryption\EncryptorInterface;

class OneOGraphQLClient
{
    /**
     * @var GraphQLFactory
     */
    private $graphQLClient;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var PasetoToken
     */
    public $pasetoToken;

    /**
     * @var KatalysToken
     */
    private $katalysToken;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param PasetoToken $pasetoToken
     * @param EncryptorInterface $encryptor
     * @param KatalysToken $katalysToken
     * @param GraphQLFactory $graphQLClient
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PasetoToken $pasetoToken,
        EncryptorInterface $encryptor,
        KatalysToken $katalysToken,
        GraphQLFactory $graphQLClient
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->pasetoToken = $pasetoToken;
        $this->encryptor = $encryptor;
        $this->katalysToken = $katalysToken;
        $this->graphQLClient = $graphQLClient;
    }

    /**
     * @return GraphQL
     * @throws \Exception
     */
    public function getClient()
    {
        $url = $this->scopeConfig->getValue("oneo/general/oneo_url", 'store');
        $keyId = $this->scopeConfig->getValue("oneo/general/key_id", 'store');
        $sharedSecret = $this->encryptor->decrypt($this->scopeConfig->getValue("oneo/general/shared_secret", 'store'));

        $bearerToken = $this->pasetoToken->getSignedToken($sharedSecret, '{"kid":"'.$keyId.'"}');
        $this->katalysToken->setSecret($sharedSecret)->setKeyId($keyId);

        if (!$url) {
            throw new \Exception("Please set OneO Url");
        }
        return $this->graphQLClient->create(
            ['url' => $url, 'bearerToken' => $bearerToken, 'katalysToken' => $this->katalysToken]
        );
    }

}
<?php

namespace OneO\Shop\Model;

class OneOGraphQLClient
{

    private \OneO\Model\GraphQL $graphQLClient;
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;
    public \OneO\Model\PasetoToken $pasetoToken;
    private \Magento\Framework\Encryption\EncryptorInterface $encryptor;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \OneO\Model\PasetoToken $pasetoToken
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \OneO\Model\PasetoToken $pasetoToken,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->pasetoToken = $pasetoToken;
        $this->encryptor = $encryptor;
    }

    public function getClient()
    {

        $url = $this->scopeConfig->getValue("oneo/general/oneo_url", 'store');
        $keyId = $this->scopeConfig->getValue("oneo/general/key_id", 'store');
        $sharedSecret = base64_decode($this->encryptor->decrypt($this->scopeConfig->getValue("oneo/general/shared_secret", 'store')));

        $bearerToken = $this->pasetoToken->getSignedToken($sharedSecret, '{"kid":"'.$keyId.'"}');

        if (!$url) {
            throw new \Exception("Please set OneO Url");
        }
        return new \OneO\Model\GraphQL($url, $bearerToken);
    }

}
<?php

declare(strict_types=1);

namespace OneO\Shop\Controller\Index;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestContentInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use OneO\Shop\Model\ProcessCompleteOrderDirective;
use OneO\Shop\Model\ProcessHealthCheckDirective;
use OneO\Shop\Model\ProcessImportProductDirective;
use OneO\Shop\Model\ProcessUpdateAvailableShippingRatesDirective;
use OneO\Shop\Model\ProcessUpdateTaxAmountsDirective;

class Index implements CsrfAwareActionInterface
{
    const AUTH_VERSION = 'v2';
    const AUTH_PURPOSE = 'local';

    const DIRECTIVES_JSON_KEY = 'directives';
    const DIRECTIVE_JSON_KEY = 'directive';

    const DIRECTIVE_HEALTH_CHECK = 'health_check';
    const DIRECTIVE_IMPORT_PRODUCT = 'import_product_from_url';
    const DIRECTIVE_UPDATE_AVAILABLE_SHIPPING_RATES = 'update_available_shipping_rates';
    const DIRECTIVE_UPDATE_TAX_AMOUNTS = 'update_tax_amounts';
    const DIRECTIVE_COMPLETE_ORDER = 'complete_order';

    private JsonFactory $jsonFactory;
    private RequestContentInterface $request;
    private JsonSerializer $jsonSerializer;
    private ProcessImportProductDirective $processImportProductDirective;
    private ProcessUpdateAvailableShippingRatesDirective $processUpdateAvailableShippingRatesDirective;
    private ProcessHealthCheckDirective $processHealthCheckDirective;
    private ProcessUpdateTaxAmountsDirective $processUpdateTaxAmountsDirective;
    private ProcessCompleteOrderDirective $processCompleteOrderDirective;
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;
    private \Magento\Framework\Encryption\EncryptorInterface $encryptor;
    private \OneO\Model\PasetoToken $pasetoToken;

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestContentInterface $request
     * @param JsonSerializer $jsonSerializer
     * @param ProcessHealthCheckDirective $processHealthCheckDirective
     * @param ProcessImportProductDirective $processImportProductDirective
     * @param ProcessUpdateAvailableShippingRatesDirective $processUpdateAvailableShippingRatesDirective
     * @param ProcessUpdateTaxAmountsDirective $processUpdateTaxAmountsDirective
     * @param ProcessCompleteOrderDirective $processCompleteOrderDirective
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \OneO\Model\PasetoToken $pasetoToken
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestContentInterface $request,
        JsonSerializer $jsonSerializer,
        ProcessHealthCheckDirective $processHealthCheckDirective,
        ProcessImportProductDirective $processImportProductDirective,
        ProcessUpdateAvailableShippingRatesDirective $processUpdateAvailableShippingRatesDirective,
        ProcessUpdateTaxAmountsDirective $processUpdateTaxAmountsDirective,
        ProcessCompleteOrderDirective $processCompleteOrderDirective,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \OneO\Model\PasetoToken $pasetoToken
    )
    {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->jsonSerializer = $jsonSerializer;
        $this->processImportProductDirective = $processImportProductDirective;
        $this->processUpdateAvailableShippingRatesDirective = $processUpdateAvailableShippingRatesDirective;
        $this->processHealthCheckDirective = $processHealthCheckDirective;
        $this->processUpdateTaxAmountsDirective = $processUpdateTaxAmountsDirective;
        $this->processCompleteOrderDirective = $processCompleteOrderDirective;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->pasetoToken = $pasetoToken;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $jsonBody = $this->jsonSerializer->unserialize($this->request->getContent());
        $directivesToProcess = $jsonBody[self::DIRECTIVES_JSON_KEY];

        $processedDirectives = [];

        foreach ($directivesToProcess as $directive) {
            /** @var \OneO\Shop\Api\Data\ProcessDirectiveInterface | null $processor */
            $processor = null;
            switch ($directive[self::DIRECTIVE_JSON_KEY]) {
                case self::DIRECTIVE_HEALTH_CHECK:
                    $processor = $this->processHealthCheckDirective;
                    break;
                case self::DIRECTIVE_IMPORT_PRODUCT:
                    $processor = $this->processImportProductDirective;
                    break;
                case self::DIRECTIVE_UPDATE_AVAILABLE_SHIPPING_RATES:
                    $processor = $this->processUpdateAvailableShippingRatesDirective;
                    break;
                case self::DIRECTIVE_UPDATE_TAX_AMOUNTS:
                    $processor = $this->processUpdateTaxAmountsDirective;
                    break;
                case self::DIRECTIVE_COMPLETE_ORDER:
                    $processor = $this->processCompleteOrderDirective;
            }

            if ($processor === null) {
                $processedDirectives[] = [
                    "in_response_to" => $directive[self::DIRECTIVE_JSON_KEY],
                    "status" => "Unimplemented directive " . $directive[self::DIRECTIVE_JSON_KEY]
                ];
            } else {
                try {
                    $processedDirectives[] = array_merge(
                        [
                            "in_response_to" => $directive[self::DIRECTIVE_JSON_KEY]
                        ],
                        $processor->processDirective($directive)
                    );

                } catch (\Exception $e) {
                    $processedDirectives[] = [
                        "in_response_to" => $directive[self::DIRECTIVE_JSON_KEY],
                        "status" => $e->getMessage()
                    ];
                }
            }
        }

        $result = $this->jsonFactory->create();
        return $result->setData(
            [
                "results" => $processedDirectives
            ]
        );
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return new \Magento\Framework\App\Request\InvalidRequestException(
            new \Magento\Framework\Exception\NotFoundException(__("Not Allowed")),
            [__("You are not authorized to access this resource.")]
        );
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool {
        $authorizationToken = str_replace("Bearer ", "", $request->getHeader('Authorization'));
        list($version, $purpose, , $footer) = explode(".", $authorizationToken);

        if ($version !== self::AUTH_VERSION) {
            return false;
        }

        if ($purpose !== self::AUTH_PURPOSE) {
            return false;
        }

        $keyId = $this->scopeConfig->getValue("oneo/general/key_id", 'store');
        $sharedSecret = $this->encryptor->decrypt($this->scopeConfig->getValue("oneo/general/shared_secret", 'store'));

        $decodedFooter = json_decode(base64_decode($footer), true);
        $receivedKid = $decodedFooter["kid"];
        if ($receivedKid !== $keyId) {
            return false;
        }

        return $this->pasetoToken->verifyToken($authorizationToken, $sharedSecret, $footer);
    }
}


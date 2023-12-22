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
use OneO\Shop\Model\ProcessProductInformationSyncDirective;
use OneO\Shop\Model\ProcessUpdateAvailabilityDirective;
use OneO\Shop\Model\ProcessUpdateAvailableShippingRatesDirective;
use OneO\Shop\Model\ProcessUpdateTaxAmountsDirective;
use OneO\Model\PasetoToken;
use OneO\Model\KatalysToken;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Index class
 */
class Index implements CsrfAwareActionInterface
{
    /**
     * @const string
     */
    const AUTH_VERSION = 'v2';

    /**
     * @const string
     */
    const AUTH_PURPOSE = 'local';

    /**
     * @const string
     */
    const DIRECTIVES_JSON_KEY = 'directives';

    /**
     * @const string
     */
    const DIRECTIVE_JSON_KEY = 'directive';

    /**
     * @const string
     */
    const DIRECTIVE_ID_JSON_KEY = 'id';

    /**
     * @const string
     */
    const DIRECTIVE_HEALTH_CHECK = 'health_check';

    /**
     * @const string
     */
    const DIRECTIVE_IMPORT_PRODUCT = 'import_product_from_url';

    /**
     * @const string
     */
    const DIRECTIVE_UPDATE_AVAILABLE_SHIPPING_RATES = 'update_available_shipping_rates';

    /**
     * @const string
     */
    const DIRECTIVE_UPDATE_TAX_AMOUNTS = 'update_tax_amounts';

    /**
     * @const string
     */
    const DIRECTIVE_UPDATE_AVAILABILITIES = 'update_availability';

    /**
     * @const string
     */
    const DIRECTIVE_COMPLETE_ORDER = 'complete_order';

    /**
     * @const string
     */
    const DIRECTIVE_PRODUCT_INFORMATION_SYNC = 'product_information_sync';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var RequestContentInterface
     */
    private $request;

    /**
     * @var JsonSerializer
     */
    private $jsonSerializer;

    /**
     * @var ProcessImportProductDirective
     */
    private $processImportProductDirective;

    /**
     * @var ProcessUpdateAvailableShippingRatesDirective
     */
    private $processUpdateAvailableShippingRatesDirective;

    /**
     * @var ProcessHealthCheckDirective
     */
    private $processHealthCheckDirective;

    /**
     * @var ProcessUpdateTaxAmountsDirective
     */
    private $processUpdateTaxAmountsDirective;

    /**
     * @var ProcessCompleteOrderDirective
     */
    private $processCompleteOrderDirective;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var PasetoToken
     */
    private $pasetoToken;

    /**
     * @var KatalysToken
     */
    private $katalysToken;

    /**
     * @var ProcessUpdateAvailabilityDirective
     */
    private $processUpdateAvailabilityDirective;

    /**
     * @var ProcessProductInformationSyncDirective
     */
    private $processProductInformationSyncDirective;

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestContentInterface $request
     * @param JsonSerializer $jsonSerializer
     * @param ProcessHealthCheckDirective $processHealthCheckDirective
     * @param ProcessImportProductDirective $processImportProductDirective
     * @param ProcessUpdateAvailableShippingRatesDirective $processUpdateAvailableShippingRatesDirective
     * @param ProcessUpdateTaxAmountsDirective $processUpdateTaxAmountsDirective
     * @param ProcessCompleteOrderDirective $processCompleteOrderDirective
     * @param ProcessUpdateAvailabilityDirective $processUpdateAvailabilityDirective
     * @param ProcessProductInformationSyncDirective $processProductInformationSyncDirective
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param PasetoToken $pasetoToken
     * @param KatalysToken $katalysToken
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
        ProcessUpdateAvailabilityDirective $processUpdateAvailabilityDirective,
        ProcessProductInformationSyncDirective $processProductInformationSyncDirective,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        PasetoToken $pasetoToken,
        KatalysToken $katalysToken
    ) {
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
        $this->processUpdateAvailabilityDirective = $processUpdateAvailabilityDirective;
        $this->katalysToken = $katalysToken;
        $this->processProductInformationSyncDirective = $processProductInformationSyncDirective;
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
                case self::DIRECTIVE_UPDATE_AVAILABILITIES:
                    $processor = $this->processUpdateAvailabilityDirective;
                    break;
                case self::DIRECTIVE_COMPLETE_ORDER:
                    $processor = $this->processCompleteOrderDirective;
                    break;
                case self::DIRECTIVE_PRODUCT_INFORMATION_SYNC:
                    $processor = $this->processProductInformationSyncDirective;
                    break;
            }

            if ($processor === null) {
                $processedDirectives[] = [
                    "source_directive" => $directive[self::DIRECTIVE_JSON_KEY],
                    "source_id" => $directive[self::DIRECTIVE_ID_JSON_KEY],
                    "status" => "Unimplemented directive " . $directive[self::DIRECTIVE_JSON_KEY]
                ];
            } else {
                try {
                    $processedDirectives[] = array_merge(
                        [
                            "source_directive" => $directive[self::DIRECTIVE_JSON_KEY],
                            "source_id" => $directive[self::DIRECTIVE_ID_JSON_KEY]
                        ],
                        $processor->processDirective($directive)
                    );

                } catch (\Exception $e) {
                    $processedDirectives[] = [
                        "source_directive" => $directive[self::DIRECTIVE_JSON_KEY],
                        "source_id" => $directive[self::DIRECTIVE_ID_JSON_KEY],
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
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        if (!$request->getHeader('Authorization')) {
            return false;
        }

        $authorizationToken = str_replace("Bearer ", "", $request->getHeader('Authorization'));
        list($version, $purpose, , $footer) = explode(".", $authorizationToken);

        if ($version !== self::AUTH_VERSION) {
            return false;
        }

        if ($purpose !== self::AUTH_PURPOSE) {
            return false;
        }

        $keyId = $this->scopeConfig->getValue("oneo/general/key_id", 'store');
        $decodedFooter = json_decode(base64_decode($footer), true);
        $receivedKid = $decodedFooter["kid"];
        if ($receivedKid !== $keyId) {
            return false;
        }

        return $this->validateTokens($authorizationToken, $footer, $request);
    }

    /**
     * @param $authorizationToken
     * @param $footer
     * @param RequestInterface $request
     * @return bool
     */
    protected function validateTokens($authorizationToken, $footer, RequestInterface $request): bool
    {
        $keyId = $this->scopeConfig->getValue("oneo/general/key_id", 'store');
        $sharedSecret = $this->encryptor->decrypt($this->scopeConfig->getValue("oneo/general/shared_secret", 'store'));

        if (!$this->pasetoToken->verifyToken($authorizationToken, $sharedSecret, $footer)) {
            return false;
        }

        if (!$request->getHeader('x-katalys-token')) {
            return true;
        }

        $this->katalysToken->setSecret($sharedSecret)->setKeyId($keyId);
        if (!$this->katalysToken->verifyToken($request->getHeader('x-katalys-token'), $this->request->getContent())) {
            return false;
        }
        return true;
    }
}


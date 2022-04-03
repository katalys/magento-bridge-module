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

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestContentInterface $request
     * @param JsonSerializer $jsonSerializer
     * @param ProcessHealthCheckDirective $processHealthCheckDirective
     * @param ProcessImportProductDirective $processImportProductDirective
     * @param ProcessUpdateAvailableShippingRatesDirective $processUpdateAvailableShippingRatesDirective
     * @param ProcessUpdateTaxAmountsDirective $processUpdateTaxAmountsDirective
     * @param ProcessCompleteOrderDirective $processCompleteOrderDirective
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestContentInterface $request,
        JsonSerializer $jsonSerializer,
        ProcessHealthCheckDirective $processHealthCheckDirective,
        ProcessImportProductDirective $processImportProductDirective,
        ProcessUpdateAvailableShippingRatesDirective $processUpdateAvailableShippingRatesDirective,
        ProcessUpdateTaxAmountsDirective $processUpdateTaxAmountsDirective,
        ProcessCompleteOrderDirective $processCompleteOrderDirective
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
                    $processedDirectives[] = [
                        "in_response_to" => $directive[self::DIRECTIVE_JSON_KEY],
                        "status" => $processor->processDirective($directive)
                    ];
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
        return null;
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
        return true;
    }
}


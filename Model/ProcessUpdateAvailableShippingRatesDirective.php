<?php

declare(strict_types=1);

namespace Katalys\Shop\Model;

use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Katalys\Shop\Api\Data\ProcessDirectiveInterface;
use Katalys\Shop\Helper\CartInitializer;
use Katalys\Shop\Model\OneOGraphQLClient;

/**
 * ProcessUpdateAvailableShippingRatesDirective class
 */
class ProcessUpdateAvailableShippingRatesDirective implements ProcessDirectiveInterface
{
    /**
     * @var string
     */
    const ORDER_ID_KEY = 'order_id';

    /**
     * @var OneOGraphQLClient
     */
    private $graphQLClient;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * @var ExtensibleDataObjectConverter
     */
    private $dataObjectConverter;

    /**
     * @var ShippingMethodConverter
     */
    private $shippingMethodConverter;

    /**
     * @var CartInitializer
     */
    private $cartInitializer;

    /**
     * @param \Katalys\Shop\Model\OneOGraphQLClient $graphQLClient
     * @param TotalsCollector $totalsCollector
     * @param ExtensibleDataObjectConverter $dataObjectConverter
     * @param ShippingMethodConverter $shippingMethodConverter
     * @param CartInitializer $cartInitializer
     */
    public function __construct(
        OneOGraphQLClient $graphQLClient,
        TotalsCollector $totalsCollector,
        ExtensibleDataObjectConverter $dataObjectConverter,
        ShippingMethodConverter $shippingMethodConverter,
        CartInitializer $cartInitializer
    ) {
        $this->graphQLClient = $graphQLClient;
        $this->totalsCollector = $totalsCollector;
        $this->dataObjectConverter = $dataObjectConverter;
        $this->shippingMethodConverter = $shippingMethodConverter;
        $this->cartInitializer = $cartInitializer;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): array
    {
        $arguments = $jsonDirective[self::ARGS_KEY];
        $orderId = $arguments[self::ORDER_ID_KEY];

        $graphQlClient = $this->graphQLClient->getClient();
        $oneOOrder = $graphQlClient->getOrderDetails($orderId);

        $cart = $this->cartInitializer->initializeCartFrom1oOrder($oneOOrder);
        $methods = $this->getAvailableShippingMethods($cart);

        $oneOShippingRates = [];
        foreach ($methods as $method) {
            $oneOShippingRates[] = [
                "handle" => $method["carrier_code"] . "_" . $method["method_code"],
                "title" => $method["carrier_title"],
                "amount" => $method["amount"]["value"] * 100
            ];
        }

        $this->graphQLClient->getClient()->updateShippingRates($orderId, $oneOShippingRates);

        return ['status' => 'ok'];
    }

    private function getAvailableShippingMethods($cart)
    {
        // Calculate available shipping rates
        $shippingAddress = $cart->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true);
        $this->totalsCollector->collectAddressTotals($cart, $shippingAddress);
        $shippingRates = $shippingAddress->getGroupedAllShippingRates();
        $methods = [];
        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methodData = $this->dataObjectConverter->toFlatArray(
                    $this->shippingMethodConverter->modelToDataObject($rate, $cart->getQuoteCurrencyCode()),
                    [],
                    ShippingMethodInterface::class
                );

                $methods[] = $this->processMoneyTypeData(
                    $methodData,
                    $cart->getQuoteCurrencyCode()
                );
            }
        }

        return $methods;
    }

    /**
     * Process money type data
     *
     * @param array $data
     * @param string $quoteCurrencyCode
     * @return array
     */
    private function processMoneyTypeData(array $data, string $quoteCurrencyCode): array
    {
        if (isset($data['amount'])) {
            $data['amount'] = ['value' => $data['amount'], 'currency' => $quoteCurrencyCode];
        }

        /** @deprecated The field should not be used on the storefront */
        $data['base_amount'] = null;

        if (isset($data['price_excl_tax'])) {
            $data['price_excl_tax'] = ['value' => $data['price_excl_tax'], 'currency' => $quoteCurrencyCode];
        }

        if (isset($data['price_incl_tax'])) {
            $data['price_incl_tax'] = ['value' => $data['price_incl_tax'], 'currency' => $quoteCurrencyCode];
        }
        return $data;
    }
}

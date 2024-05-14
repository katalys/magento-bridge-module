<?php

declare(strict_types=1);

namespace Katalys\Shop\Model;

use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Katalys\Shop\Api\Data\ProcessDirectiveInterface;
use Katalys\Shop\Helper\CartInitializer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Katalys\Shop\Model\OneOGraphQLClient;

/**
 * ProcessCompleteOrderDirective class
 */
class ProcessCompleteOrderDirective implements ProcessDirectiveInterface
{
    /**
     * @const string
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
     * @var CartInitializer
     */
    private $cartInitializer;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param \Katalys\Shop\Model\OneOGraphQLClient $graphQLClient
     * @param TotalsCollector $totalsCollector
     * @param ExtensibleDataObjectConverter $dataObjectConverter
     * @param CartInitializer $cartInitializer
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OneOGraphQLClient $graphQLClient,
        TotalsCollector $totalsCollector,
        ExtensibleDataObjectConverter $dataObjectConverter,
        CartInitializer $cartInitializer,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->graphQLClient = $graphQLClient;
        $this->totalsCollector = $totalsCollector;
        $this->dataObjectConverter = $dataObjectConverter;
        $this->cartInitializer = $cartInitializer;
        $this->orderRepository = $orderRepository;
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
        if ($oneOOrder["externalId"]) {
            try {
                $order = $this->orderRepository->get($oneOOrder["externalId"]);
                return ["status" => "exists"];
            } catch(NoSuchEntityException $e) {}
        }

        $cart = $this->cartInitializer->initializeCartFrom1oOrder($oneOOrder);
        $magentoOrderId = $this->cartInitializer->completeOrder($cart->getId());
        $order = $this->orderRepository->get($magentoOrderId);
        $order->setStatus("complete")->setState("complete");
        $this->orderRepository->save($order);

        $graphQlClient = $this->graphQLClient->getClient();
        $graphQlClient->completeOrder($orderId, $magentoOrderId);
        return ['status' => 'ok'];
    }
}

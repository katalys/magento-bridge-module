<?php

declare(strict_types=1);

namespace OneO\Shop\Model;

use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\AddProductsToCart as AddProductsToCartService;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use OneO\Shop\Api\Data\ProcessDirectiveInterface;

class ProcessCompleteOrderDirective implements ProcessDirectiveInterface
{
    const ORDER_ID_KEY = 'order_id';
    private OneOGraphQLClient $graphQLClient;
    private TotalsCollector $totalsCollector;
    private ExtensibleDataObjectConverter $dataObjectConverter;
    private \OneO\Shop\Helper\CartInitializer $cartInitializer;
    private \Magento\Sales\Api\OrderRepositoryInterface $orderRepository;

    /**
     * @param OneOGraphQLClient $graphQLClient
     * @param TotalsCollector $totalsCollector
     * @param ExtensibleDataObjectConverter $dataObjectConverter
     * @param \OneO\Shop\Helper\CartInitializer $cartInitializer
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \OneO\Shop\Model\OneOGraphQLClient $graphQLClient,
        TotalsCollector $totalsCollector,
        ExtensibleDataObjectConverter $dataObjectConverter,
        \OneO\Shop\Helper\CartInitializer $cartInitializer,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    )
    {
        $this->graphQLClient = $graphQLClient;
        $this->totalsCollector = $totalsCollector;
        $this->dataObjectConverter = $dataObjectConverter;
        $this->cartInitializer = $cartInitializer;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processDirective($jsonDirective): string
    {
        $arguments = $jsonDirective[self::ARGS_KEY];
        $orderId = $arguments[self::ORDER_ID_KEY];

        $graphQlClient = $this->graphQLClient->getClient();
        $oneOOrder = $graphQlClient->getOrderDetails($orderId);

        $shouldCreateOrder = false;

        if ($oneOOrder["externalId"]) {
            try {
                $order = $this->orderRepository->get($oneOOrder["externalId"]);
                return "exists";
            } catch(NoSuchEntityException $e){
                $shouldCreateOrder = true;
            }
        } else {
            $shouldCreateOrder = true;
        }

        if ($shouldCreateOrder) {
            $cart = $this->cartInitializer->initializeCartFrom1oOrder($oneOOrder);
            $this->totalsCollector->collect($cart);

            $magentoOrderId = $this->cartInitializer->completeOrder($cart->getId());
            $order = $this->orderRepository->get($magentoOrderId);
            $order->setStatus("complete")->setState("complete");
            $this->orderRepository->save($order);

            $graphQlClient = $this->graphQLClient->getClient();
            $graphQlClient->completeOrder($orderId, $magentoOrderId);
        }

        return 'ok';
    }
}
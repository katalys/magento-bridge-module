<?php

namespace OneO\Shop\Helper;

use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Quote\Model\Cart\AddProductsToCart as AddProductsToCartService;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Payment\Api\Data\PaymentMethodInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use OneO\Shop\Model\KatalysQuoteItemFactory as KatalysQuoteItemModelFactory;
use OneO\Shop\Model\ResourceModel\KatalysQuoteItem as KatalysQuoteItemResourceModel;

/**
 * CartInitializer class
 */
class CartInitializer
{
    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteId
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var ShippingAddressManagementInterface
     */
    private $shippingAddressManagement;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressInterfaceFactory;

    /**
     * @var AddProductsToCartService
     */
    private $addProductsToCart;

    /**
     * @var BillingAddressManagementInterface
     */
    private $billingAddressManagement;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var PaymentMethodInterfaceFactory
     */
    private $paymentMethodInterfaceFactory;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Configurable
     */
    private $configurableType;

    /**
     * @var KatalysQuoteItemModelFactory
     */
    private $katalysQuoteItemModelFactory;

    /**
     * @var KatalysQuoteItemResourceModel
     */
    private $katalysQuoteItemResourceModel;

    /**
     * @param GuestCartManagementInterface $guestCartManagement
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param AddressInterfaceFactory $addressInterfaceFactory
     * @param AddProductsToCartService $addProductsToCart
     * @param BillingAddressManagementInterface $billingAddressManagement
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param PaymentMethodInterfaceFactory $paymentMethodInterfaceFactory
     * @param QuoteFactory $quoteFactory
     * @param ProductRepositoryInterface $productRepository
     * @param Configurable $configurableType
     */
    public function __construct(
        GuestCartManagementInterface $guestCartManagement,
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        ShippingAddressManagementInterface $shippingAddressManagement,
        AddressInterfaceFactory $addressInterfaceFactory,
        AddProductsToCartService $addProductsToCart,
        BillingAddressManagementInterface $billingAddressManagement,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        PaymentMethodInterfaceFactory $paymentMethodInterfaceFactory,
        QuoteFactory $quoteFactory,
        ProductRepositoryInterface $productRepository,
        Configurable $configurableType,
        KatalysQuoteItemModelFactory $katalysQuoteItemModelFactory,
        KatalysQuoteItemResourceModel $katalysQuoteItemResourceModel
    ) {
        $this->guestCartManagement = $guestCartManagement;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->addProductsToCart = $addProductsToCart;
        $this->billingAddressManagement = $billingAddressManagement;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->paymentMethodInterfaceFactory = $paymentMethodInterfaceFactory;
        $this->quoteFactory = $quoteFactory;
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
        $this->katalysQuoteItemModelFactory = $katalysQuoteItemModelFactory;
        $this->katalysQuoteItemResourceModel = $katalysQuoteItemResourceModel;
    }

    /**
     * @param array $oneOOrder
     * @return mixed
     */
    public function initializeCartFrom1oOrder($oneOOrder)
    {
        $cartId = $this->guestCartManagement->createEmptyCart();
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $cart = $this->quoteFactory->create()->loadActive($quoteId);

        // Set shipping address on cart
        /** @var \Magento\Quote\Api\Data\AddressInterface $shippingAddress */
        $shippingAddress = $this->addressInterfaceFactory->create();
        $shippingAddress->setEmail($oneOOrder["shippingEmail"]);

        $nameParts = explode(" ", $oneOOrder["shippingName"]);
        $firstname = $nameParts[0];
        unset($nameParts[0]);
        $lastname = isset($nameParts[1]) ? implode(" ", $nameParts) : "N/A";

        $shippingAddress->setFirstname($firstname);
        $shippingAddress->setLastname($lastname);
        $shippingAddress->setPostcode($oneOOrder["shippingAddressZip"]);
        $shippingAddress->setCity($oneOOrder["shippingAddressCity"]);
        $shippingAddress->setCountryId($oneOOrder["shippingAddressCountryCode"]);
        $shippingAddress->setStreet($oneOOrder["shippingAddressLine_1"] . "\n" . $oneOOrder["shippingAddressLine_2"]);
        $shippingAddress->setRegion($oneOOrder["shippingAddressSubdivision"]);

        $parsedSubdivision = explode("-", $oneOOrder["shippingAddressSubdivisionCode"] ?? "");
        $shippingAddress->setRegionCode(array_pop($parsedSubdivision));
        $shippingAddress->setTelephone($oneOOrder["shippingPhone"]);
        // Set shipping method
        $shippingAddress->setShippingMethod($oneOOrder["chosenShippingRateHandle"]);
        $this->shippingAddressManagement->assign($quoteId, $shippingAddress);
        $shippingAddress->setQuote($cart);

        // Set billing address on cart
        /** @var \Magento\Quote\Api\Data\AddressInterface $billingAddress */
        $billingAddress = $this->addressInterfaceFactory->create();
        $billingAddress->setEmail($oneOOrder["billingEmail"]);

        $nameParts = explode(" ", $oneOOrder["billingName"] ?? "");
        $firstname = $nameParts[0];
        unset($nameParts[0]);
        $lastname = isset($nameParts[1]) ? implode(" ", $nameParts) : "N/A";

        $billingAddress->setFirstname($firstname);
        $billingAddress->setLastname($lastname);
        $billingAddress->setPostcode($oneOOrder["billingAddressZip"]);
        $billingAddress->setCity($oneOOrder["billingAddressCity"]);
        $billingAddress->setCountryId($oneOOrder["billingAddressCountryCode"]);
        $billingAddress->setStreet($oneOOrder["billingAddressLine_1"] . "\n" . $oneOOrder["billingAddressLine_2"]);
        $billingAddress->setRegion($oneOOrder["billingAddressSubdivision"]);

        $parsedSubdivision = explode("-", $oneOOrder["billingAddressSubdivisionCode"] ?? "");
        $billingAddress->setRegionCode(array_pop($parsedSubdivision));
        $billingAddress->setTelephone($oneOOrder["billingPhone"]);
        $this->billingAddressManagement->assign($quoteId, $billingAddress);
        $billingAddress->setQuote($cart);
        $productData = [];
        foreach ($oneOOrder["lineItems"] as $oneOItem) {
            if (
                isset($oneOItem["variantExternalId"])
                && $oneOItem["variantExternalId"] !== $oneOItem["productExternalId"]
            ) {
                $simpleProduct = $this->productRepository->get($oneOItem["variantExternalId"]);
                $configurableProduct = $this->productRepository->get($oneOItem["productExternalId"]);
                $productAttributeOptions = $this->configurableType->getConfigurableAttributesAsArray($configurableProduct);

                $options = [];
                foreach ($productAttributeOptions as $option) {
                    $options[$option['attribute_id']] =  $simpleProduct->getData($option['attribute_code']);
                }
                $buyRequest = new \Magento\Framework\DataObject([
                    'super_attribute' => $options,
                    'qty' => $oneOItem["quantity"],
                ]);
                $cart->addProduct($configurableProduct, $buyRequest);
                $productData[$configurableProduct->getId()] = [
                    'price' => $oneOItem['price'],
                    'qty' => $oneOItem["quantity"]
                ];
            } else {
                $product = $this->productRepository->get($oneOItem["productExternalId"]);
                $buyRequest = new \Magento\Framework\DataObject(['qty' => $oneOItem["quantity"]]);
                $cart->addProduct($product, $buyRequest);
                $productData[$product->getId()] = [
                    'price' => $oneOItem['price'],
                    'qty' => $oneOItem["quantity"]
                ];
            }
            $cart->collectTotals();
            $this->cartRepository->save($cart);
        }

        $cart = $this->cartRepository->get($quoteId);
        $this->saveKatalysQuoteItem($productData, $cart);
        return $cart;
    }

    /**
     * @param int $quoteId
     * @return mixed
     */
    public function completeOrder($quoteId)
    {
        $maskedId = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        $quote = $this->cartRepository->get($quoteId);

        // TODO: This is a dummy payment to let the order pass - should be replaced with OneO specific information
        $quote->getPayment()->importData(['method' => 'checkmo']);
        return $this->guestCartManagement->placeOrder($maskedId);
    }

    /**
     * @param array $productData
     * @param $cart
     * @return void
     */
    protected function saveKatalysQuoteItem(array $productData, $cart)
    {
        foreach ($cart->getAllItems() as $item) {
            $productId = $item->getProduct()->getId();
            if (!isset($productData[$productId])) {
                continue;
            }

            if (!isset($productData[$productId]['qty']) || !isset($productData[$productId]['price'])) {
                continue;
            }
            $model = $this->katalysQuoteItemModelFactory->create();
            $model->setQuoteId($cart->getId());
            $model->setQuoteItemId($item->getItemId());
            $model->setPrice($productData[$productId]['price'] / 100);
            $model->setQty($productData[$productId]['qty']);
            $this->katalysQuoteItemResourceModel->save($model);
        }
    }
}

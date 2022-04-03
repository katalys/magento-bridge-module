<?php

namespace OneO\Shop\Helper;

use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Quote\Model\Cart\AddProductsToCart as AddProductsToCartService;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use OneO\Shop\Model\OneOGraphQLClient;

class CartInitializer
{
    private \Magento\Quote\Api\GuestCartManagementInterface $guestCartManagement;
    private \Magento\Quote\Api\CartRepositoryInterface $cartRepository;
    private \Magento\Quote\Model\MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId;
    private \Magento\Quote\Model\ShippingAddressManagementInterface $shippingAddressManagement;
    private \Magento\Quote\Api\Data\AddressInterfaceFactory $addressInterfaceFactory;
    private AddProductsToCartService $addProductsToCart;
    private \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement;
    private \Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;
    private \Magento\Payment\Api\Data\PaymentMethodInterfaceFactory $paymentMethodInterfaceFactory;

    /**
     * @param \Magento\Quote\Api\GuestCartManagementInterface $guestCartManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param \Magento\Quote\Model\ShippingAddressManagementInterface $shippingAddressManagement
     * @param \Magento\Quote\Api\Data\AddressInterfaceFactory $addressInterfaceFactory
     * @param AddProductsToCartService $addProductsToCart
     * @param \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement
     * @param \Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param \Magento\Payment\Api\Data\PaymentMethodInterfaceFactory $paymentMethodInterfaceFactory
     */
    public function __construct(
        \Magento\Quote\Api\GuestCartManagementInterface $guestCartManagement,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        \Magento\Quote\Model\ShippingAddressManagementInterface $shippingAddressManagement,
        \Magento\Quote\Api\Data\AddressInterfaceFactory $addressInterfaceFactory,
        AddProductsToCartService $addProductsToCart,
        \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        \Magento\Payment\Api\Data\PaymentMethodInterfaceFactory $paymentMethodInterfaceFactory
    )
    {
        $this->guestCartManagement = $guestCartManagement;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->addProductsToCart = $addProductsToCart;
        $this->billingAddressManagement = $billingAddressManagement;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->paymentMethodInterfaceFactory = $paymentMethodInterfaceFactory;
    }

    public function initializeCartFrom1oOrder($oneOOrder)
    {
        $cartId = $this->guestCartManagement->createEmptyCart();
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $cart = $this->cartRepository->get($quoteId);

        // Set shipping address on cart
        /** @var \Magento\Quote\Api\Data\AddressInterface $shippingAddress */
        $shippingAddress = $this->addressInterfaceFactory->create();
        $shippingAddress->setEmail($oneOOrder["shippingEmail"]);

        $nameParts = explode(" ", $oneOOrder["shippingName"]);
        $firstname = $nameParts[0];
        unset($nameParts[0]);
        $lastname = isset($nameParts[1]) ? implode(" ", $nameParts) : "";

        $shippingAddress->setFirstname($firstname);
        $shippingAddress->setLastname($lastname);
        $shippingAddress->setPostcode($oneOOrder["shippingAddressZip"]);
        $shippingAddress->setCity($oneOOrder["shippingAddressCity"]);
        $shippingAddress->setCountryId($oneOOrder["shippingAddressCountryCode"]);
        $shippingAddress->setStreet([$oneOOrder["shippingAddressLine_1"], $oneOOrder["shippingAddressLine_2"]]);
        $shippingAddress->setRegion($oneOOrder["shippingAddressSubdivision"]);
        $shippingAddress->setRegionCode($oneOOrder["shippingAddressSubdivisionCode"]);
        $shippingAddress->setTelephone($oneOOrder["shippingPhone"]);
        // Set shipping method
        $shippingAddress->setShippingMethod($oneOOrder["chosenShippingRateHandle"]);
        $this->shippingAddressManagement->assign($quoteId, $shippingAddress);
        $shippingAddress->setQuote($cart);

        // Set billing address on cart
        /** @var \Magento\Quote\Api\Data\AddressInterface $billingAddress */
        $billingAddress = $this->addressInterfaceFactory->create();
        $billingAddress->setEmail($oneOOrder["billingEmail"]);

        $nameParts = explode(" ", $oneOOrder["billingName"]);
        $firstname = $nameParts[0];
        unset($nameParts[0]);
        $lastname = isset($nameParts[1]) ? implode(" ", $nameParts) : "";

        $billingAddress->setFirstname($firstname);
        $billingAddress->setLastname($lastname);
        $billingAddress->setPostcode($oneOOrder["billingAddressZip"]);
        $billingAddress->setCity($oneOOrder["billingAddressCity"]);
        $billingAddress->setCountryId($oneOOrder["billingAddressCountryCode"]);
        $billingAddress->setStreet([$oneOOrder["billingAddressLine_1"], $oneOOrder["billingAddressLine_2"]]);
        $billingAddress->setRegion($oneOOrder["billingAddressSubdivision"]);
        $billingAddress->setRegionCode($oneOOrder["billingAddressSubdivisionCode"]);
        $billingAddress->setTelephone($oneOOrder["billingPhone"]);
        $this->billingAddressManagement->assign($quoteId, $billingAddress);
        $billingAddress->setQuote($cart);


        // Add products to cart
        $cartItems = [];
        foreach ($oneOOrder["lineItems"] as $oneOItem) {
            $cartItemData = [
                "quantity" => $oneOItem["quantity"]
            ];
            if (isset($oneOItem["variantExternalId"])) {
                $cartItemData["sku"] = $oneOItem["variantExternalId"];
                $cartItemData["parent_sku"] = $oneOItem["productExternalId"];
            } else {
                $cartItemData["sku"] = $oneOItem["productExternalId"];
            }

            $cartItems[] = (new CartItemFactory())->create($cartItemData);
        }

        $this->addProductsToCart->execute($cartId, $cartItems);
        $cart = $this->cartRepository->get($quoteId);
        return $cart;
    }

    public function completeOrder($quoteId)
    {
        $maskedId = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        $quote = $this->cartRepository->get($quoteId);

        // TODO: This is a dummy payment to let the order pass - should be replaced with OneO specific information
        $quote->getPayment()->importData(['method' => 'checkmo']);
        return $this->guestCartManagement->placeOrder($maskedId);
    }

}
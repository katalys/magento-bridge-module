<?php

namespace Katalys\Shop\Model\Quote\Address\Total;

use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Katalys\Shop\Model\KatalysQuoteItemFactory as KatalysQuoteItemModelFactory;
use Katalys\Shop\Model\KatalysQuoteItem;
use Katalys\Shop\Model\ResourceModel\KatalysQuoteItem as KatalysQuoteItemResourceModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item;

/**
 * Class DiscountKatalys
 */
class DiscountKatalys extends AbstractTotal
{
    /**
     * @var KatalysQuoteItemModelFactory
     */
    private $katalysQuoteItemModelFactory;

    /**
     * @var KatalysQuoteItemResourceModel
     */
    private $katalysQuoteItemResourceModel;

    /**
     * @param KatalysQuoteItemModelFactory $katalysQuoteItemModelFactory
     * @param KatalysQuoteItemResourceModel $katalysQuoteItemResourceModel
     */
    public function __construct(
        KatalysQuoteItemModelFactory $katalysQuoteItemModelFactory,
        KatalysQuoteItemResourceModel $katalysQuoteItemResourceModel
    ) {
        $this->katalysQuoteItemModelFactory = $katalysQuoteItemModelFactory;
        $this->katalysQuoteItemResourceModel = $katalysQuoteItemResourceModel;
        $this->setCode('katalys_discount');
    }

    /**
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        if (!$quote->getId()) {
            return $this;
        }
        parent::collect($quote, $shippingAssignment, $total);
        $address = $shippingAssignment->getShipping()->getAddress();
        $items = $quote->getAllItems();
        $totalDiscount = 0;
        /** @var Item $item */
        foreach ($items as $item) {
            $katalysQuoteItem = $this->getKatalysQuoteItemId($item);
            if (!$katalysQuoteItem->getId()) {
                continue;
            }

            $price = $katalysQuoteItem->getPrice();
            if ($item->getProduct()->getPrice() > $price) {
                $discount = ($item->getProduct()->getPrice() - $price) * $item->getQty();
                $item->setDiscountAmount($discount);
                $item->setBaseDiscountAmount($discount);
                $item->setOriginalDiscountAmount($discount);
                $item->setBaseOriginalDiscountAmount($discount);
                $totalDiscount += $discount;
            }
        }
        $total->setSubtotalWithDiscount($total->getSubtotal() - $totalDiscount);
        $total->setBaseSubtotalWithDiscount($total->getBaseSubtotal() - $totalDiscount);
        $total->setGrandTotal($total->getGrandTotal() - $totalDiscount);
        $total->setBaseGrandTotal($total->getBaseGrandTotal() - $totalDiscount);
        $address->setBaseDiscountAmount($totalDiscount);
        $address->setDiscountAmount($totalDiscount);
        $address->setDiscountAmount($totalDiscount);
        $address->setDiscountDescription($this->getCode());
        return $this;
    }

    /**
     * @param Item $item
     * @return mixed
     */
    protected function getKatalysQuoteItemId(Item $item): KatalysQuoteItem
    {
        $model = $this->katalysQuoteItemModelFactory->create();
        $this->katalysQuoteItemResourceModel->load($model, $item->getItemId(), 'quote_item_id');
        return $model;
    }
}

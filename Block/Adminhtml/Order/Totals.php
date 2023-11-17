<?php

namespace OneO\Shop\Block\Adminhtml\Order;

use Magento\Quote\Model\ResourceModel\Quote\Item as ResourceModelQuoteItem;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Block\Adminhtml\Order\Totals as MagentoTotals;
use OneO\Shop\Model\ResourceModel\KatalysQuoteItem\Collection;

/**
 * Class Totals
 */
class Totals extends MagentoTotals
{
    /**
     * @var Collection
     */
    private $collectionKatalysQuoteItem;

    /**
     * @var ResourceModelQuoteItem
     */
    private $resourceModelQuoteItem;

    /**
     * @var ItemFactory
     */
    private $itemFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param Collection $collectionKatalysQuoteItem
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        Collection $collectionKatalysQuoteItem,
        ResourceModelQuoteItem $resourceModelQuoteItem,
        ItemFactory $itemFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $adminHelper, $data);
        $this->collectionKatalysQuoteItem = $collectionKatalysQuoteItem;
        $this->resourceModelQuoteItem = $resourceModelQuoteItem;
        $this->itemFactory = $itemFactory;
    }

    /**
     * Initialize order totals array
     *
     * @return $this
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        $discount = $this->getDiscount();
        if ($discount <= 0.00) {
            return $this;
        }
        $this->_totals['katalys_discount'] = new \Magento\Framework\DataObject(
            [
                'code' => 'katalys_discount',
                'strong' => true,
                'value' => -$discount,
                'base_value' => -$discount,
                'label' => __('Katalys Discount'),
                'area' => 'footer',
            ]
        );
        return $this;
    }

    /**
     * @return float
     */
    protected function getDiscount(): float
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getSource();
        if (!$order->getQuoteId()) {
            return 0.00;
        }
        $this->collectionKatalysQuoteItem->addFieldToFilter('quote_id', $order->getQuoteId());
        if ($this->collectionKatalysQuoteItem->getSize() <= 0) {
            return 0.00;
        }

        $discount = 0.00;
        foreach ($this->collectionKatalysQuoteItem->getItems() as $item) {
            /** @var Item $quoteItem */
            $quoteItem = $this->itemFactory->create();
            $this->resourceModelQuoteItem->load($quoteItem, $item->getQuoteItemId(), 'item_id');
            if (!$quoteItem->getItemId()) {
                continue;
            }
            $price = $item->getPrice();
            if ($quoteItem->getPrice() > $price) {
                $discount += ($quoteItem->getPrice() - $price) * $item->getQty();
            }
        }
        return (float)$discount;
    }
}

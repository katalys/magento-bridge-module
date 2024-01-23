<?php

namespace OneO\Shop\Util;

/**
 * OrderPackager class
 */
class OrderPackager
{
    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    protected $customerGroup;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected static $customerGroups;

    /**
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroup
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroup,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customerGroup = $customerGroup;
        $this->logger = $logger;

        self::$customerGroups = $this->customerGroup->toOptionHash();
    }

    /**
     * Get the params in the format that the collector endpoint expects
     *
     * @param $orderId
     * @param $isKey boolean whether to use primary [entity] id
     * @return null|array
     */
    public function getParams($orderId, $isKey = null)
    {
        if (!$orderId) {
            return null;
        }

        try {
            if ($isKey) {
                $order = $this->orderRepository->get($orderId);
            } else {
                $this->searchCriteriaBuilder->addFilter('increment_id', $orderId);
                $orders = $this->orderRepository->getList(
                    $this->searchCriteriaBuilder->create()
                )->getItems();
                if (is_array($orders) && count($orders) === 1) {
                    $order = array_shift($orders);
                } else {
                    return null;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ':' . $e->getMessage());
            return null;
        }

        return self::_mapData($order);
    }

    /**
     * @param $order
     * @return array|null
     */
    static function _mapData($order)
    {
        $data = $order->getData();
        if (!$data) {
            return null;
        }

        $params = [];

        $params['client_ip'] = $data['remote_ip'] ?? null;
        if (!empty($data[\OneO\Shop\Observer\OrderObserver::META_COLUMN])) {
            $metadata = json_decode($data[\OneO\Shop\Observer\OrderObserver::META_COLUMN], 1);
            if ($metadata) {
                $params['user_agent'] = $metadata['user_agent'];
                $params['vid'] = $metadata['vid'];
                // override remote_ip with our frontend cookie-based client_ip
                if (!empty($metadata['client_ip'])) {
                    $params['client_ip'] = $metadata['client_ip'];
                }
            }
        }

        // general order info
        $params['order_id'] = $data['increment_id'];
        $params['email_address'] = $data['customer_email'];
        $params['order_key'] = $data['entity_id'];
        $params['order_time'] = strtotime($data['created_at']);

        // map status from payment_status
        $orderStatus = $data['status'];
        $params['payment_status'] = $orderStatus;
        if ($orderStatus == "fraud") {
            $params['order_status'] = "rejected";
        } elseif ($orderStatus == "canceled") {
            $params['order_status'] = "cancelled";
        } elseif ($orderStatus == "closed") {
            $params['order_status'] = "refunded";
        }

        // money
        $params['currency'] = $data['order_currency_code'];
        $params['shipping_amount'] = $data['shipping_amount'];
        $params['sale_amount'] = $data['grand_total'];
        $params['sale_amount_with_currency'] = $data['grand_total'] . ' ' . $data['order_currency_code'];
        $params['tax_amount'] = $data['tax_amount'];
        $params['subtotal_amount'] = $data['subtotal'] + $data['discount_amount']; // as discussed

        // addresses
        $address = $order->getBillingAddress();
        if ($address) {
            $params['billing_city'] = $address->getData('city');
            $params['billing_state'] = $address->getData('region');
            $params['billing_postal'] = $address->getData('postcode');
        }
        $address = $order->getShippingAddress();
        if ($address) {
            $params['shipping_city'] = $address->getData('city');
            $params['shipping_state'] = $address->getData('region');
            $params['shipping_postal'] = $address->getData('postcode');
        }

        // line items
        $i = 0;
        foreach ($order->getAllItems() as $item) {
            $params["line_item_{$i}_title"] = $item->getData('name');
            // $params["line_item_{$i}_var"] =  '';
            $params["line_item_{$i}_qty"] = $item->getData('qty_ordered');
            $params["line_item_{$i}_sku"] = $item->getData('sku');
            $params["line_item_{$i}_price"] = $item->getData('price');
            $params["line_item_{$i}_categories"] = $item->getData('product_type');

            $i++;
        }

        // discount
        $params['discount_1_code'] = $data['coupon_code'] ?? null;
        $params['discount_1_amt'] = abs($data['discount_amount']);

        if (self::$customerGroups !== null) {
            $cgid = $order->getCustomerGroupId();
            $params['customer_group'] = self::$customerGroups[$cgid] ?? 'unknown';
        }

        return $params;
    }
}
<?php

namespace Katalys\Shop\Plugin\Quote\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\CouponManagement;

/**
 * Class CouponManagementPlugin
 */
class CouponManagementPlugin
{
    /**
     * @param CouponManagement $subject
     * @param callable $proceed
     * @param $cartId
     * @param $couponCode
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundSet(
        CouponManagement $subject,
        callable $proceed,
        $cartId,
        $couponCode
    ) {
        if (substr($couponCode, 0, 3) === 'KS_') {
            throw new \Exception(__("The coupon code isn't valid. Verify the code and try again."));
        }
        return $proceed($cartId, $couponCode);
    }
}

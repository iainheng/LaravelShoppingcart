<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface MinRequiredShippingCouponDiscountable extends ShippingCouponDiscountable
{
    /**
     * Get minimum total amount required of eligible cart items
     *
     * @return float
     */
    public function getMinRequiredAmount();
}

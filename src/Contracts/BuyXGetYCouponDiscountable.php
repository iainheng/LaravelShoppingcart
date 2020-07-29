<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface BuyXGetYCouponDiscountable extends MinRequiredDiscountable
{
    /**
     * Get the identifiers of the requirement products to have in cart in order to apply discount for items
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDiscountableRequiredIdentifiers($options = null);

    /**
     * Get quantity of cart items that will be applied with discount
     *
     * @return int
     */
    public function getReceiveQuantity();
}

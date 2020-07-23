<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface BuyXGetYCouponDiscountable extends CouponDiscountable
{
    /**
     * Get the identifiers of the requirement products to have in cart in order to apply discount for items
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDiscountableRequiredIdentifiers($options = null);

    /**
     * Get minimum quantity required of eligible cart items
     *
     * @return int
     */
    public function getMinRequiredQuantity();

    /**
     * Get minimum total amount required of eligible cart items
     *
     * @return float
     */
    public function getMinRequiredAmount();

    /**
     * Get quantity of cart items that will be applied with discount
     *
     * @return int
     */
    public function getReceiveQuantity();
}

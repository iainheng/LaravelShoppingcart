<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface MinRequiredDiscountable extends CouponDiscountable
{
    /**
     * Get spend type requirement of discountable eithen amount or quantity
     * @return string
     */
    public function getRequiredSpendType();

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
}

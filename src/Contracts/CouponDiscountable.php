<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface CouponDiscountable
{
    /**
     * Get the identifiers of the CouponDiscountable item.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDiscountableIdentifiers($options = null);

    /**
     * Get the description or title of the CouponDiscountable item.
     *
     * @return string
     */
    public function getDiscountableDescription($options = null);
}

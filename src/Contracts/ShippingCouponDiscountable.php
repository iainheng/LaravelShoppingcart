<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface ShippingCouponDiscountable extends CouponDiscountable
{
    /**
     * Get the identifiers of the allowed countries for shipping discount
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllowedCountries($options = null);
}

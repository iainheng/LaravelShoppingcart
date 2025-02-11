<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface VoucherDiscountable
{
    /**
     * Get the identifiers of the CouponDiscountable item.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getVoucherDiscountableIdentifiers($options = null);

    /**
     * Get the description or title of the CouponDiscountable item.
     *
     * @return string
     */
    public function getVoucherDiscountableDescription($options = null);
}

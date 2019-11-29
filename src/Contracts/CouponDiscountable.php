<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Gloudemans\Shoppingcart\Cart;

interface CouponDiscountable
{
    /**
     * Get the identifiers of the CouponDiscountable item.
     *
     * @param Cart $cart
     * @return \Illuminate\Support\Collection
     */
    public function getDiscountableIdentifiers(Cart $cart, $options = null);

    /**
     * Get the description or title of the CouponDiscountable item.
     *
     * @param Cart|null $cart
     * @param null $options
     * @return string
     */
    public function getDiscountableDescription(Cart $cart = null, $options = null);
}

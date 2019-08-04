<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\Couponable;

class OrderCoupon extends CartCoupon
{
    public function __construct(
        $code,
        $value,
        $percentageDiscount = false,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $options = []
    ) {
        parent::__construct($code, $value, $percentageDiscount, $dateFrom, $dateTo, $options);
    }

    /**
     * If an item is supplied it will get its discount value.
     *
     * @param CartItem $item
     *
     * @return float
     */
    public function forItem(CartItem $item)
    {
        $amount = $item->price;

        if (config('cart.discount.tax_item_before_discount')) {
            $amount = $item->priceTax * $this->getDiscountValue();
        }

        return ($this->percentageDiscount) ? $amount * $this->getDiscountValue() : $this->getDiscountValue();
    }

    /**
     *
     * @param Cart $cart
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        $str = parent::getDescription($cart, $options);

        $validValueFrom = $this->options->get('valid_value');

        if (is_numeric($validValueFrom))
            $str .= ' for orders above RM '. $this->numberFormat($validValueFrom);
        else
            $str .= ' for all orders';

        return $str;
    }


    /**
     * Gets the discount amount.
     *
     * @param Cart $cart
     * @param $throwErrors boolean this allows us to capture errors in our code if we wish,
     * that way we can spit out why the coupon has failed
     *
     * @return float
     */
    public function discount(Cart $cart, $throwErrors = false)
    {
        parent::discount($cart, $throwErrors);

        $subTotal = $cart->getDiscountableFloat();

//        $discountedAmount = $cart->coupons()->filter(function (Couponable $coupon) {
//            return $coupon->getCode() != $this->getCode();
//        })->sum(function (Couponable $coupon) use ($cart) {
//            return $coupon->discount($cart);
//        });

        $validValueFrom = $this->options->get('valid_value');

        if (is_numeric($validValueFrom))
            $this->checkMinAmount($cart, $validValueFrom);

//            throw new CouponException(ucfirst(config('cart.discount.coupon_label')) . ' only applicable for order value equal or above '. $this->numberFormat($validValueFrom));

        $currentDiscountAmount = ($this->percentageDiscount) ? $subTotal * $this->getDiscountValue() : $this->getDiscountValue();

//        if ($subTotal - $currentDiscountAmount < 0) $currentDiscountAmount = $subTotal;

        return $currentDiscountAmount;
    }
}

<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\BuyXGetYCouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\MinRequiredDiscountable;
use Gloudemans\Shoppingcart\Contracts\MinRequiredShippingCouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\ShippingCouponDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Exceptions\NoAmountToDiscountException;
use Gloudemans\Shoppingcart\Traits\ItemCouponTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MinRequiredShippingCoupon extends ShippingCoupon
{
    public function __construct(
        $code,
        $value,
        MinRequiredShippingCouponDiscountable $discountable,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $percentageDiscount = false,
        $options = []
    ) {
        parent::__construct($code, $value, $discountable, $dateFrom, $dateTo, $percentageDiscount, $options);
    }

    protected function validate(Cart $cart)
    {
        parent::validate($cart);

        $requiredAmount = $this->discountable->getMinRequiredAmount();
        $requiredAmountMode = is_numeric($requiredAmount);
        $requiredQuantity = $this->discountable->getMinRequiredQuantity();
        $requiredQuantityMode = is_numeric($requiredQuantity);

        $shippingTotal = $cart->shippingFloat();
        $orderTotal = $cart->itemsTotal();
        $orderItemsQuantity = $cart->items()->sum('qty');

        if ($shippingTotal <= 0) {
            throw new NoAmountToDiscountException("Cart does not contain any shipping cost.");
        }

        if (($requiredAmountMode && $orderTotal < $requiredAmount)) {
            throw new CouponException("Your cart items does not meet requirements of this discount.");
        }

        if (($requiredQuantityMode && $orderItemsQuantity < $requiredQuantity)) {
            throw new CouponException("Your cart items does not meet requirements of this discount.");
        }

        // we apply discount only if current cart shipping value is less than amount we can afford
        if ($this->options->get('valid_value') && $shippingTotal > $this->options->get('valid_value')) {
            throw new CouponException('Your cart shipping cost exceeded ' . config('cart.discount.coupon_label') . ' limit.');
        }

        $discountableCountryIds = $this->discountable->getAllowedCountries();

        // if restrict shipping discount to certain countries
        if ($discountableCountryIds->isNotEmpty()) {
            $shippingCountryId = $this->getShippingCountryId($cart);

            if (!$shippingCountryId) {
                throw new CouponException("Invalid shipping country");
            }

            if (!in_array($shippingCountryId, $discountableCountryIds->all())) {
                throw new CouponException(ucfirst(config('cart.discount.coupon_label')). ' can only be used on shipping address from ' . $this->discountable->getDiscountableDescription());
            }
        }
    }

    public function discount(Cart $cart, $throwErrors = true)
    {
        $shippingTotal = parent::discount($cart, $throwErrors);

        $currentDiscountAmount = ($this->percentageDiscount) ? $shippingTotal * $this->getDiscountValue() : $this->getDiscountValue();

        return $currentDiscountAmount;
    }

    /**@inheritdoc */
    public function isShipping()
    {
        return true;
    }

    /**
     *
     * @param Cart $cart
     * @param array $options
     * @return string
     */
//    public function getDescription(Cart $cart = null, $options = [])
//    {
//        $str = parent::getDescription($cart, $options);
//
//        if ($this->discountable && $this->discountable->getRequiredSpendType()) {
//            $requiredAmount = $this->discountable->getMinRequiredAmount();
//            $requiredQuantity = $this->discountable->getMinRequiredQuantity();
//            $requiredAmountMode = $this->discountable->getRequiredSpendType() == 'amount';
//
//            if ($requiredAmountMode) {
//                $str .= ($requiredAmount > 0) ? ' for orders above RM ' . $this->numberFormat($requiredAmount) . '.' : ' for all orders';
//            } else {
//                $str .= ' for orders with minimum quantity of ' . $requiredQuantity . '.';
//            }
//        } else {
//            // all below are now deprecated and will be remove in the future.
//            $validValueFrom = $this->options->get('valid_value');
//
//            if (is_numeric($validValueFrom)) {
//                $str .= ' for orders above RM ' . $this->numberFormat($validValueFrom);
//            } else {
//                $str .= ' for all orders';
//            }
//        }
//
//        return $str;
//    }
}

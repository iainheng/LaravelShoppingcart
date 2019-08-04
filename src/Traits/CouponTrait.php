<?php

namespace Gloudemans\Shoppingcart\Traits;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Exceptions\CouponException;

/**
 * Class CouponTrait.
 */
trait CouponTrait
{
    /**
     * @var bool
     */
    public $applyToCart = true;

    public $percentageDiscount = false;

    protected $type;

//    use CartOptionsMagicMethodsTrait;

    /**
     * Sets all the options for the coupon.
     *
     * @param $options
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Apply current coupon discount to cart or cart items that will change cart or cart items amount permanently
     *
     * @param Cart $cart
     * @return float
     * @throws CouponException
     */
    public function apply(Cart $cart, $throwErrors = true)
    {}

    /**
     * Forget current coupon discount to cart or cart items that revert changes to cart or cart items amount
     *
     * @param Cart $cart
     * @param bool $throwErrors
     * @return float
     * @throws CouponException
     */
    public function forget(Cart $cart, $throwErrors = true)
    {}

    /**
     * Checks to see if we can apply the coupon.
     *
     * @return bool
     */
    public function canApply(Cart $cart)
    {
        try {
            $this->discount($cart, true);

            return true;
        } catch (CouponException $e) {
            return false;
        }
    }

    /**
     * Gets the failed message for a coupon.
     *
     * @return null|string
     */
    public function getFailedMessage()
    {
        try {
            $this->discount(true);
        } catch (CouponException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Checks the minimum subtotal needed to apply the coupon.
     *
     * @param $minAmount
     * @param $throwErrors
     *
     * @return bool
     * @throws CouponException
     *
     */
    public function checkMinAmount(Cart $cart, $minAmount, $throwErrors = true)
    {
        $subTotal = $cart->subtotalFloat();

        if (config('cart.discount.discount_on_fees', false)) {
            $subTotal = $subTotal + $cart->feesTotal(false);
        }

        if ($subTotal < $minAmount) {
            if ($throwErrors) {
                throw new CouponException(ucfirst(config('cart.discount.coupon_label')) . ' is only applicable for order value equal or above ' . $this->numberFormat($minAmount));
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns either the max discount or the discount applied based on what is passed through.
     *
     * @param $maxDiscount
     * @param $discount
     * @param $throwErrors
     *
     * @return mixed
     * @throws CouponException
     *
     */
    public function maxDiscount($maxDiscount, $discount, $throwErrors = true)
    {
        if ($maxDiscount == 0 || $maxDiscount > $discount) {
            return $discount;
        } else {
            if ($throwErrors) {
                throw new CouponException(ucfirst(config('cart.discount.coupon_label')) . ' has a max discount of ' . $this->numberFormat($maxDiscount));
            } else {
                return $maxDiscount;
            }
        }
    }

    /**
     * Checks to see if the times are valid for the coupon.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param $throwErrors
     *
     * @return bool
     * @throws CouponException
     *
     */
    public function checkValidTimes(Carbon $startDate, Carbon $endDate, $throwErrors = true)
    {
        if (Carbon::now()->between($startDate, $endDate)) {
            return true;
        } else {
            if ($throwErrors) {
                throw new CouponException('This ' . config('cart.discount.coupon_label') . ' has expired');
            } else {
                return false;
            }
        }
    }

    /**
     * Perform some basic checkings and throw exception when requirement is not met
     *
     * @return bool
     * @throws CouponException
     */
    protected function validate()
    {
        if ($this->dateTo && Carbon::now()->greaterThan($this->dateTo)) {
            throw new CouponException(ucfirst(config('cart.discount.coupon_label')) . ' has expired');
        }

        if ($this->dateFrom && Carbon::now()->lessThan($this->dateFrom)) {
            throw new CouponException(ucfirst(config('cart.discount.coupon_label')) . ' is not activated yet and will be effective after ' . $this->dateFrom->format(config('cart.date_format')));
        }

        return true;
    }

    /**
     * Check if coupon discount is apply to cart or items
     *
     * @return bool
     */
    public function isApplyToCart()
    {
        return $this->applyToCart;
    }
}

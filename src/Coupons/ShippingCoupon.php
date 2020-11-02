<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\ShippingCouponDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Illuminate\Support\Arr;

class ShippingCoupon extends CartCoupon
{
    /**
     * @var CouponDiscountable
     */
    protected $discountable;

    public function __construct(
        $code,
        $value,
        ShippingCouponDiscountable $discountable,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $percentageDiscount = false,
        $options = []
    )
    {
        parent::__construct($code, $value, $percentageDiscount, $dateFrom, $dateTo, $options);

        $this->discountable = $discountable;
        $this->type = self::TYPE_SHIPPING;
    }

    /**
     * If an item is supplied it will get its discount value for single quantity
     *
     * @param CartItem $item
     *
     * @return float
     */
    public function forItem(CartItem $item)
    {
        throw new \BadMethodCallException('Shipping coupon is not appliable to item.');
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
    public function discount(Cart $cart, $throwErrors = true)
    {
        parent::discount($cart, $throwErrors);

        $shippingTotal = $cart->shippingFloat();

        // we apply discount only if current cart shipping value is less than amount we can afford
        if ($this->options->get('valid_value') && $shippingTotal > $this->options->get('valid_value')) {
            throw new CouponException('Your cart shipping cost exceeded ' . config('cart.discount.coupon_label') . ' limit.');
        }

        $discountableCountryIds = $this->discountable->getAllowedCountries();

        // if restrict shipping discount to certain countries
        if ($discountableCountryIds->isNotEmpty()) {
            $shippingCountryId = $this->getShippingCountryId($cart);

            if (!$shippingCountryId && $throwErrors) {
                throw new CouponException("Invalid shipping country");
            }

            if (!in_array($shippingCountryId, $discountableCountryIds->all())) {
                throw new CouponException(ucfirst(config('cart.discount.coupon_label')). ' can only be used on shipping address from ' . $this->discountable->getDiscountableDescription());
            }
        }

        return $shippingTotal;
    }

    /**
     * Get shipping country id currently in cart
     *
     * @param Cart $cart
     * @return int
     */
    protected function getShippingCountryId(Cart $cart)
    {
        $shippingAddress = $cart->getAttribute(config('cart.session.shipping'));

        return Arr::get($shippingAddress, 'address.country_id');
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $data = parent::toArray();

        $data['discountable'] = $this->discountable;

        return $data;
    }

    /**
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        $discountableDescription = $this->discountable->getDiscountableDescription();

        return 'Free shipping'. (!empty($discountableDescription) ? ' to ' . $discountableDescription : '');
    }

    /**@inheritdoc */
    public function isShipping()
    {
        return true;
    }
}

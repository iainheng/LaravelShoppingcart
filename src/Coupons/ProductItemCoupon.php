<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Traits\ItemCouponTrait;

class ProductItemCoupon extends CartCoupon
{
    use ItemCouponTrait;

    public function __construct(
        $code,
        $value,
        CouponDiscountable $discountable,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $percentageDiscount = false,
        $applyOnce = false,
        $options = []
    )
    {
        parent::__construct($code, $value, $percentageDiscount, $dateFrom, $dateTo, $options);

        $this->applyOnce = $applyOnce;
        $this->applyToCart = false;

        $this->associate($discountable);
    }

    /**
     * Apply current coupon discount to cart or cart items that will change cart or cart items amount permanently
     *
     * @param Cart $cart
     * @return float
     * @throws CouponException
     */
    public function apply(Cart $cart, $throwErrors = true)
    {
        $this->validate();

        $discountableCartItems = $this->getDiscountableCartItems($cart);

        if ($discountableCartItems->isEmpty() && $throwErrors) {
            throw new CouponException("Your cart does not contain items from " . $this->discountable->getDiscountableDescription($cart));
        }

        if ($discountableCartItems->isNotEmpty()) {
            if ($this->applyOnce) {
                $lowestPriceItem = $discountableCartItems->where('priceTax', $discountableCartItems->min('priceTax'))->first();

                $this->setDiscountOnItem($lowestPriceItem);
            } else {
                foreach ($discountableCartItems as $cartItem) {
                    $this->setDiscountOnItem($cartItem);
                }
            }
        }
    }

    /**
     * Forget current coupon discount to cart or cart items that revert changes to cart or cart items amount
     *
     * @param Cart $cart
     * @throws CouponException
     */
    public function forget(Cart $cart, $throwErrors = true)
    {
        $discountableCartItems = $this->getDiscountableCartItems($cart);

        if ($discountableCartItems->isNotEmpty()) {
            if ($this->applyOnce) {
                $lowestPriceItem = $discountableCartItems->where('priceTax', $discountableCartItems->min('priceTax'))->first();

                $this->removeDiscountOnItem($lowestPriceItem);
            } else {
                foreach ($discountableCartItems as $cartItem) {
                    $this->removeDiscountOnItem($cartItem);
                }
            }
        }
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
        $amount = $item->price;

        if (config('cart.discount.tax_item_before_discount')) {
            $amount = $item->priceTax * $this->getDiscountValue();
        }

        return ($this->percentageDiscount) ? $amount * $this->getDiscountValue() : $this->getDiscountValue();
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

        return $cart->items()->reduce(function ($total, CartItem $cartItem) {
            return $total + $cartItem->discountTotal;
        }, 0);
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
        $data['apply_once'] = $this->applyOnce;

        return $data;
    }

    /**
     * Get list of cart items that are eligible for discount
     *
     * @param Cart $cart
     * @return \Illuminate\Support\Collection|CartItem[]
     */
    protected function getDiscountableCartItems(Cart $cart)
    {
        $discountableIds = $this->discountable->getDiscountableIdentifiers($cart);

        return $cart->search(function (CartItem $cartItem) use ($discountableIds) {
            return in_array($cartItem->id, $discountableIds->all());
        });
    }

    /**
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        $str = parent::getDescription($cart, $options) . ' for ' . $this->discountable->getDiscountableDescription($cart);

        if ($this->applyOnce)
            $str .= ' (once per order)';

        return $str;
    }
}

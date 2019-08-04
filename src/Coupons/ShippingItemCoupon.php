<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Traits\ItemCouponTrait;

class ShippingItemCoupon extends CartCoupon
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

        $this->discountable = $discountable;
        $this->applyOnce = $applyOnce;
        $this->applyToCart = true;
        $this->type = self::TYPE_SHIPPING;
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

        $shippingTotal = $cart->shippingFloat();

        // we apply discount only if current cart shipping value is less than amount we can afford
        if ($this->options->get('valid_value') && $shippingTotal > $this->options->get('valid_value')) {
            throw new CouponException('Your cart shipping cost exceeded ' . config('cart.discount.coupon_label') . ' limit.');
        }

        $discountableCartItems = $this->getDiscountableCartItems($cart);

        if ($discountableCartItems->isEmpty() && $throwErrors) {
            throw new CouponException("Your cart does not contain items from " . $this->discountable->getDiscountableDescription());
        }

        if ($discountableCartItems->isNotEmpty()) {
            foreach ($discountableCartItems as $cartItem) {
                $this->setShippingDiscountOnItem($cartItem);
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
            foreach ($discountableCartItems as $cartItem) {
                $this->removeShippingDiscountOnItem($cartItem);
            }
        }
    }

    /**
     * Sets a shipping discount to an item with what code was used and the discount amount.
     *
     * @param CartItem $item
     * @param float|null $discount
     */
    public function setShippingDiscountOnItem(CartItem $item, $discount = null)
    {
//        $this->applyToCart = false;

        if (!$discount)
            $discount = $item->options->get('shipping_cost');

        $item->options->put('shipping_discount', $discount);
    }

    /**
     * Remove shipping discount to an item with what code was used and the discount amount.
     *
     * @param CartItem $item
     * @param float|null $discount
     */
    public function removeShippingDiscountOnItem(CartItem $item)
    {
        $item->options->pull('shipping_discount');
    }

    /**
     * @todo Check and remove if unnecessary
     *
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

        return $cart->itemShippingsFloat();
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
        $discountableIds = $this->discountable->getDiscountableIdentifiers();

        // if discountable is keyword * which means we discount every item in cart
        if ($discountableIds->count() == 1 && $discountableIds->first() == '*')
            return $cart->items();

        return $cart->search(function (CartItem $cartItem) {
            return in_array($cartItem->id, $this->discountable->getDiscountableIdentifiers()->all());
        });
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
}

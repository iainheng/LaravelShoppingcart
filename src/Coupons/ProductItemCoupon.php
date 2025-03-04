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
        $this->validate($cart);

        $requiredAmount = $this->discountable->getMinRequiredAmount();

        $discountableCartItems = $this->getDiscountableCartItems($cart);

        $totalCartItemsAmount = $discountableCartItems->sum(function($item) {
            return $item->total;
        });

        if (!is_null($requiredAmount)) {
            $this->checkMinAmount($cart, $requiredAmount);
        }

        if ($discountableCartItems->isEmpty() && $throwErrors) {
            throw new CouponException("Your cart does not contain items from " . $this->discountable->getDiscountableDescription());
        }

        if ($discountableCartItems->isNotEmpty()) {
            if (!$this->percentageDiscount && $this->applyOnce) {
//                $lowestPriceItem = $discountableCartItems->where('price', $discountableCartItems->min('price'))->first();
//
//                $this->setDiscountOnItem($lowestPriceItem);

                // we split the total discount amount
                $decimals = config('cart.format.decimals', 2);

                foreach ($discountableCartItems as $cartItem) {
                    // prevent override previous coupon if it was discounted before
                    if (!$cartItem->coupon) {
                        $this->applyToCart = false;

                        $valueAfterDivided = round($cartItem->price / $totalCartItemsAmount * $this->value, $decimals);

                        $cartItem->setDiscount($valueAfterDivided, $this->percentageDiscount, $this->applyOnce);

                        $cartItem->setCoupon($this);
                    }
                }
            } else {
                $appliedQuantity = 0;

                foreach ($discountableCartItems as $cartItem) {
                    if ($this->applyOnce && $appliedQuantity > 0) {
                        break;
                    }

                    // prevent override previous coupon if it was discounted before
                    if (!$cartItem->coupon) {
                        $this->setDiscountOnItem($cartItem);

                        $appliedQuantity++;
                    }
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
//            if ($this->applyOnce) {
//                $lowestPriceItem = $discountableCartItems->where('price', $discountableCartItems->min('price'))->first();
//
//                $this->removeDiscountOnItem($lowestPriceItem);
//            } else {
                foreach ($discountableCartItems as $cartItem) {
                    if ($cartItem->coupon && $cartItem->coupon->code == $this->getCode()) {
                        $this->removeDiscountOnItem($cartItem);
                    }
                }
//            }
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
            return $total + (($cartItem->coupon == $this) ? $cartItem->discountTotal : 0);
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
        $str = parent::getDescription($cart, $options) . $this->discountable->getDiscountableDescription();

        if ($this->applyOnce)
            $str .= ' (once per order)';

        return $str;
    }
}

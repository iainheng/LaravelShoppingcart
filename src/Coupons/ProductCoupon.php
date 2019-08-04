<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;

class ProductCoupon extends CartCoupon
{
    /**
     * @var CouponDiscountable
     */
    protected $product;

    /**
     * @var bool
     */
    protected $applyOnce;


    public function __construct(
        $code,
        $value,
        CouponDiscountable $product,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $percentageDiscount = false,
        $applyOnce = false,
        $options = []
    )
    {
        parent::__construct($code, $value, $percentageDiscount, $dateFrom, $dateTo, $options);

        $this->product = $product;
        $this->applyOnce = $applyOnce;
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

        $amount = 0;

        $discountableCartItems = $this->getDiscountableCartItems($cart);

        if ($discountableCartItems->isEmpty() && $throwErrors) {
            throw new CouponException("Your cart does not contain items from " . $this->product->getDiscountableDescription());
        }

        if ($discountableCartItems->isNotEmpty()) {
            if ($this->applyOnce) {
                $lowestPriceItem = $discountableCartItems->where('priceTax', $discountableCartItems->min('priceTax'))->first();

                $amount = $this->forItem($lowestPriceItem);
            } else {
                foreach ($discountableCartItems as $cartItem) {
                    $amount += $cartItem->qty * $this->forItem($cartItem);
                }
            }
        }

        return $amount;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $data = parent::toArray();

        $data['product'] = $this->product;
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
        return $cart->search(function (CartItem $cartItem) {
            return in_array($cartItem->id, $this->product->getDiscountableIdentifiers()->all());
        });
    }

    /**
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        $str = parent::getDescription($cart, $options) . ' for ' . $this->product->getDiscountableDescription();

        if ($cart) {
            $discountableCartItems = $this->getDiscountableCartItems($cart);
            $str = $discountableCartItems->count() . ' x ' . $str;
        }
        
        return $str;
    }
}

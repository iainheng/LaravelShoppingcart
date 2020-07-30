<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\BuyXGetYCouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Traits\ItemCouponTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property-read BuyXGetYCouponDiscountable $discountable
 */
class BuyXGetYCoupon extends ProductItemCoupon
{
    public function __construct(
        $code,
        $value,
        BuyXGetYCouponDiscountable $discountable,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $percentageDiscount = false,
        $applyOnce = false,
        $options = []
    ) {
        parent::__construct($code, $value, $discountable, $dateFrom, $dateTo, $percentageDiscount, $applyOnce,
            $options);
    }

    protected function validate(Cart $cart)
    {
        parent::validate($cart);

        $requiredCartItems = $this->getRequiredCartItems($cart);
        $discountableCartItems = $this->getDiscountableCartItems($cart);

        $requiredAmount = $this->discountable->getMinRequiredAmount();
        $requiredQuantity = $this->discountable->getMinRequiredQuantity();
        $receivedQuantity = $this->discountable->getReceiveQuantity();

        $requiredAmountMode = is_numeric($requiredAmount);

        $intersectItemsQuantities = $discountableCartItems->groupBy('id')->map(function (
            Collection $collection,
            $id
        ) use ($requiredCartItems) {
            return $requiredCartItems->sum(function (CartItem $cartItem) use ($id) {
                return ($cartItem->id == $id) ? $cartItem->qty : 0;
            });
        });

        $totalEligibleCartItems = $requiredCartItems->sum('qty') + $discountableCartItems->sum('qty') - $intersectItemsQuantities->sum();

//        dump($requiredQuantity, $receivedQuantity, $totalEligibleCartItems);

        list($fullPriceQty, $discountPriceQty) = $this->getFullAndDiscountQuantityBreakdown($requiredQuantity,
            $receivedQuantity, $totalEligibleCartItems);

//        dump($fullPriceQty, $discountPriceQty);

        if ((!$requiredAmountMode && $requiredCartItems->sum('qty') + ($discountableCartItems->sum('qty') - $discountPriceQty) < $fullPriceQty) ||
            ($requiredAmountMode && !$requiredCartItems->sum('priceTax') >= $requiredAmount)) {
            throw new CouponException("Your cart items does not meet requirements of this discount.");
        }

        if ($discountPriceQty <= 0) {
            throw new CouponException("Your cart does not contain enough item quantity to get this discount.");
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
    {
        $this->validate($cart);

        $requiredCartItems = $this->getRequiredCartItems($cart);
        $discountableCartItems = $this->getDiscountableCartItems($cart);

        $requiredAmount = $this->discountable->getMinRequiredAmount();
        $requiredQuantity = $this->discountable->getMinRequiredQuantity();
        $receivedQuantity = $this->discountable->getReceiveQuantity();

        $requiredAmountMode = is_numeric($requiredAmount);

        $intersectItemsQuantities = $discountableCartItems->groupBy('id')->map(function (
            Collection $collection,
            $id
        ) use ($requiredCartItems) {
            return $requiredCartItems->sum(function (CartItem $cartItem) use ($id) {
                return ($cartItem->id == $id) ? $cartItem->qty : 0;
            });
        });

        $totalEligibleCartItems = $requiredCartItems->sum('qty') + $discountableCartItems->sum('qty') - $intersectItemsQuantities->sum();

        list($fullPriceQty, $discountPriceQty) = $this->getFullAndDiscountQuantityBreakdown($requiredQuantity,
            $receivedQuantity, $totalEligibleCartItems);

//        dump($discountableCartItems, 'discountableCartItems');

        foreach ($discountableCartItems as $cartItem) {
            if ($discountPriceQty > 0) {
                // if cartItem quantity is more than discount qty, we need to split this cart item into different rows
                if ($discountPriceQty < $cartItem->qty) {
                    //$splitedCartItem = $cart->add($cartItem->id . '-' . Str::random(4), $cartItem->name, $discountPriceQty, $cartItem->price, $cartItem->weight, (array) $cartItem->options);
                    $splitedCartItem = $cartItem->duplicate($cartItem->id . '-' . Str::random(4));

                    $cart->addCartItem($splitedCartItem);
                    $splitedCartItem->setQuantity($discountPriceQty);

                    $splitedCartItem->setDiscount($this->value, $this->percentageDiscount, $this->applyOnce);
                    $splitedCartItem->setCoupon($this);
                    $splitedCartItem->id = $cartItem->id;

                    $cartItem->setQuantity($cartItem->qty - $splitedCartItem->qty);

                    $discountPriceQty -= $discountPriceQty;
                } else {
                    $cartItem->setDiscount($this->value, $this->percentageDiscount, $this->applyOnce);
                    $cartItem->setCoupon($this);

                    $discountPriceQty -= $cartItem->qty;
                }
            }
        }

        $this->mergeCartItems($cart);
    }

    /**
     * Check and merge any cart items that are same id, price and coupon applied
     *
     * @param Cart $cart
     */
    protected function mergeCartItems(Cart $cart)
    {
//        dd($cart->items()->groupBy(function (CartItem $item) {
//            $couponId = ($item->coupon) ? $item->coupon->getCode() : '';
//            return $item->id . $couponId;
//        }));
        foreach ($cart->items()->groupBy(function (CartItem $item) {
            $couponId = ($item->coupon) ? $item->coupon->getCode() : '';
            return $item->id . $couponId;
        }) as $id => $items) {
            if ($items->count() > 1) {
                $cartItem = $items->first();

                $cartItem->setQuantity($items->sum('qty'));

                $items->reject(function ($item) use ($cartItem) {
                    return $item->rowId == $cartItem->rowId;
                })->map(function ($item) use ($cart) {
                    $cart->remove($item->rowId);
                });
            }
        }
    }

    /**
     * Get full and price distribution for quantity of items available
     *
     * @param int $requiredQty
     * @param int $receivedQty
     * @param int $totalQty
     * @return array 1st item is full price quantity, 2nd item is discount price quantity
     */
    protected function getFullAndDiscountQuantityBreakdown($requiredQty, $receivedQty, $totalQty)
    {
//          //formula not working for 7 + 1 case
//
//            $r = $totalQty % ($requiredQty + $receivedQty);
//
//            $n = ($totalQty - $r) / ($requiredQty + $receivedQty);
//
//            $py = max(0, $r - $requiredQty) + ($n * $receivedQty);
//            $px = $totalQty - $py;

        $pack = $requiredQty + $receivedQty;
        $buyPacks = floor($totalQty / $pack);
        $buyIndividual = $totalQty % $pack;

        $px = $requiredQty * $buyPacks + $buyIndividual;
        $py = $totalQty - $px;

        return [
            $px,
            $py
        ];
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
                $lowestPriceItem = $discountableCartItems->where('priceTax',
                    $discountableCartItems->min('priceTax'))->first();

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
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        $str = parent::getDescription($cart, $options) . ' for ' . $this->discountable->getDiscountableDescription();

        if ($this->applyOnce) {
            $str .= ' (once per order)';
        }

        return $str;
    }

    /**
     * Get list of cart items that are eligible for discount
     *
     * @param Cart $cart
     * @return \Illuminate\Support\Collection|CartItem[]
     */
    protected function getRequiredCartItems(Cart $cart)
    {
        return $cart->search(function (CartItem $cartItem) {
            return in_array($cartItem->id, $this->discountable->getDiscountableRequiredIdentifiers()->all());
        });
    }
}

<?php

namespace Gloudemans\Shoppingcart\Coupons;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\BuyXGetYCouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\MinRequiredDiscountable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Traits\ItemCouponTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property-read MinRequiredDiscountable $discountable
 */
class MinRequiredItemCoupon extends ProductItemCoupon
{
    public function __construct(
        $code,
        $value,
        MinRequiredDiscountable $discountable,
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

        $requiredAmount = $this->discountable->getMinRequiredAmount();
        $requiredQuantity = $this->discountable->getMinRequiredQuantity();
        $requiredAmountMode = $this->discountable->getRequiredSpendType() == 'amount';

        $discountableCartItems = $this->getDiscountableCartItems($cart);

        $totalCartItemsAmount = $discountableCartItems->sum(function($item) {
            return $item->total;
        });

        if ($discountableCartItems->isEmpty()) {
            throw new CouponException("Your cart does not contain items from " . $this->discountable->getDiscountableDescription());
        }

        if ((!$requiredAmountMode && $discountableCartItems->sum('qty') < $requiredQuantity) ||
            ($requiredAmountMode && $totalCartItemsAmount < $requiredAmount)) {
            throw new CouponException("Your cart items does not meet requirements of this discount.");
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

        $discountableCartItems = $this->getDiscountableCartItems($cart);
        $totalEligibleQuantity = $discountableCartItems->sum('qty');

        if ($discountableCartItems->isNotEmpty()) {
            if ($this->percentageDiscount) {
                if ($this->applyOnce) {
                    $lowestPriceItem = $discountableCartItems->where('priceTax',
                        $discountableCartItems->min('priceTax'))->first();

                    $this->setDiscountOnItem($lowestPriceItem);
                } else {
                    foreach ($discountableCartItems as $cartItem) {
                        $this->setDiscountOnItem($cartItem);
                    }
                }
            } else {
                foreach ($discountableCartItems as $cartItem) {
                    $this->applyToCart = false;

                    $valueAfterDivided = $this->value / $totalEligibleQuantity;

                    $cartItem->setDiscount($valueAfterDivided, $this->percentageDiscount, $this->applyOnce);

                    $cartItem->setCoupon($this);
                }
            }
        }
    }

    /**
     *
     * @param Cart $cart
     * @param array $options
     * @return string
     */
//    public function getDescription(Cart $cart = null, $options = [])
//    {
//        $str = sprintf('%s off', $this->displayValue());
//
//        $requiredAmount = $this->discountable->getMinRequiredAmount();
//        $requiredQuantity = $this->discountable->getMinRequiredQuantity();
//        $requiredAmountMode = $this->discountable->getRequiredSpendType() == 'amount';
//
//        $discountableCartItems = $this->getDiscountableCartItems($cart);
//
//        $discountableNames = $discountableCartItems->groupBy(function (CartItem $item) {
//            return $item->name;
//        })->map(function (Collection $groupedCartItems, $itemName) {
//            return sprintf('%s (%s %s)', $itemName, $groupedCartItems->count(), Str::plural('variant', $groupedCartItems->count()));
//        });
//
//        $str .= ' ' . $discountableNames->join(', ');
//
//        if ($requiredAmountMode) {
//            $str .= ' &bull; Minimum purchase amount RM ' . $this->numberFormat($requiredAmount) . '.';
//        } else {
//            $str .= ' &bull; Minimum quantity of ' . $requiredQuantity . '.';
//        }
//
//        return $str;
//    }
}

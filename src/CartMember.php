<?php

namespace Gloudemans\Shoppingcart;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Contracts\Memberable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Exceptions\MemberException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class CartMember implements Arrayable, Jsonable, Memberable
{
    protected array $attributes = [];

    /**
     * @var Carbon
     */
    protected $dateFrom;

    /**
     * @var Carbon
     */
    protected $dateTo;

    protected bool $applyToCart = false;

    /**
     * @param array $attributes
     */
    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    public function getName(): string
    {
        return data_get($this->attributes, 'name');
    }

    public function getDescription(): ?string
    {
        return data_get($this->attributes, 'description');
    }

    public function getId(): int
    {
        return data_get($this->attributes, 'id');
    }

    public function getTier(): string
    {
        return data_get($this->attributes, 'tier');
    }

    public function getDiscountRate(): float
    {
        return data_get($this->attributes, 'discount.rate');
    }

    public function getDiscountMinRequiredAmount(): ?float
    {
        return data_get($this->attributes, 'discount.min_required_amount');
    }

//    public function getDiscountProductIdentifies(): Collection
//    {
//        return collect(data_get($this->attributes, 'discount.product_ids', ['*']));
//    }

    /**
     * Get list of cart items that are eligible for discount
     *
     * @param Cart $cart
     * @return \Illuminate\Support\Collection|CartItem[]
     */
    public function getDiscountableCartItems(Cart $cart)
    {
//        $discountableIds = $this->getDiscountProductIdentifies();
//
//        // if discountable is keyword * which means we discount every item in cart
//        if ($discountableIds->count() == 1 && $discountableIds->first() == '*')
//            return $cart->items();

        return $cart->search(function (CartItem $cartItem) {
            return data_get($cartItem->options, config('cart.member.item_is_discountable_key'), false);
        });
    }

    protected function checkDiscountMinAmount(Cart $cart, $minAmount, $throwErrors = true)
    {
        $subTotal = $cart->subtotalFloat();

        if ($subTotal < $minAmount) {
            if ($throwErrors) {
                throw new MemberException(' Member discount is only applicable for order value equal or above ' . $this->numberFormat($minAmount));
            } else {
                return false;
            }
        }

        return true;
    }

    public function isPercentageDiscount(): bool
    {
        return data_get($this->attributes, 'discount.is_percentage', true);
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
                $cartItem->removeMember();
            }
        }
    }

    /**
     * Check if discount is apply to cart or items
     *
     * @return bool
     */
    public function isApplyToCart(): bool
    {
        return $this->applyToCart;
    }

    /**
     * Get discount rate
     *
     * @return float
     */
    protected function getDiscountValue()
    {
        return ($this->isPercentageDiscount()) ? $this->getDiscountRate() / 100 : $this->getDiscountRate();
    }

    public function apply(Cart $cart, $throwErrors = true)
    {
        $requiredAmount = $this->getDiscountMinRequiredAmount();

        $discountableCartItems = $this->getDiscountableCartItems($cart);

        $totalCartItemsAmount = $discountableCartItems->sum(function(CartItem $item) {
            return $item->total;
        });

        if (!is_null($requiredAmount)) {
            $this->checkDiscountMinAmount($cart, $requiredAmount);
        }

//        if ($discountableCartItems->isEmpty() && $throwErrors) {
//            throw new MemberException("Your cart does not contain items for member discount");
//        }

        if ($discountableCartItems->isNotEmpty()) {
            if (!$this->isPercentageDiscount()) {
                // we split the total discount amount
                $decimals = config('cart.format.decimals', 2);

                foreach ($discountableCartItems as $cartItem) {
                    // prevent override previous coupon if it was discounted before
                    $this->applyToCart = false;

                    $valueAfterDivided = round($cartItem->price / $totalCartItemsAmount * $this->getDiscountRate(), $decimals);

//                    $cartItem->setMemberDiscount($valueAfterDivided, $this->isPercentageDiscount());
                    $cartItem->setMember($this, $valueAfterDivided, $this->isPercentageDiscount());
                }
            } else {
                foreach ($discountableCartItems as $cartItem) {
//                    $cartItem->setMemberDiscount($this->getDiscountValue(), $this->isPercentageDiscount());
                    $cartItem->setMember($this, $this->getDiscountRate(), $this->isPercentageDiscount());
                }
            }
        }
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge($this->attributes, [
            'description' => $this->getDescription(),
        ]);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the formatted number.
     *
     * @param float  $value
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    protected function numberFormat($value, $decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        if (is_null($decimals)) {
            $decimals = config('cart.format.decimals', 2);
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = config('cart.format.decimal_point', '.');
        }

        if (is_null($thousandSeperator)) {
            $thousandSeperator = config('cart.format.thousand_separator', ',');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeperator);
    }
}

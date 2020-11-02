<?php

namespace Gloudemans\Shoppingcart;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Contracts\Couponable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Exceptions\NoAmountToDiscountException;
use Gloudemans\Shoppingcart\Traits\CouponTrait;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

abstract class CartCoupon implements Arrayable, Jsonable, Couponable
{
    use CouponTrait;

    const TYPE_ORDER_AMOUNT = 'order-amount';
    const TYPE_SHIPPING = 'shipping';

    protected $code;

    protected $value;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The options for this coupon.
     *
     * @var array
     */
    public $options;

    /**
     * @var Carbon
     */
    protected $dateFrom;

    /**
     * @var Carbon
     */
    protected $dateTo;

    /**
     * @param string $code
     * @param float $value
     * @param bool $percentageDiscount
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct(
        $code,
        $value,
        $percentageDiscount = false,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $options = []
    )
    {
        $this->type = self::TYPE_ORDER_AMOUNT;

        $this->code = $code;

        $this->value = $value;

        $this->percentageDiscount = $percentageDiscount;

        $this->dateFrom = $dateFrom;

        $this->dateTo = $dateTo;

        $this->options = new Collection($options);
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Cart $cart
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        return sprintf('%s off', $this->displayValue());
    }

    public function forItem(CartItem $cartItem)
    {
        return ($this->percentageDiscount) ? $cartItem->total * $this->value : $this->value;
    }

    /**
     * Get discount rate
     *
     * @return float
     */
    protected function getDiscountValue()
    {
        return ($this->percentageDiscount) ? $this->value / 100 : $this->value;
    }

    public function discount(Cart $cart, $throwErrors = true)
    {
        try {
            $this->validate($cart);
        } catch (NoAmountToDiscountException $noAmountToDiscountException) {
            if ($throwErrors)
                throw $noAmountToDiscountException;
        } catch (CouponException $ce) {
            if ($throwErrors)
                throw $ce;
        }
    }

    /**
     * Displays the type of value it is for the user.
     *
     * @return mixed
     */
    public function displayValue()
    {
        return ($this->percentageDiscount) ? $this->numberFormat($this->value, 0) . '%' : 'RM ' . $this->value;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'code'    => $this->code,
            'value'    => $this->value,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'percentage_discount' => $this->percentageDiscount,
            'options'  => $this->options->toArray(),
        ];
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

    /**@inheritdoc */
    public function isShipping()
    {
        return false;
    }
}

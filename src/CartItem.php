<?php

namespace Gloudemans\Shoppingcart;

use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Contracts\Couponable;
use Gloudemans\Shoppingcart\Contracts\Memberable;
use Gloudemans\Shoppingcart\Contracts\Voucherable;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;

/**
 * Class CartItem
 *
 * @package Gloudemans\Shoppingcart
 * @property Couponable $coupon
 * @property-read float discount
 * @property-read float discountTotal
 * @property-read float memberDiscount
 * @property-read float memberDiscountTotal
 * @property-read float voucherDiscountTotal
 * @property-read float allDiscountTotal
 * @property-read float priceTarget
 * @property-read float priceMember
 * @property-read float priceNet
 * @property-read float priceTotal
 * @property-read float subtotal
 * @property-read float taxTotal
 * @property-read float tax
 * @property-read float total
 * @property-read float priceTax
 */
class CartItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $qty;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public $price;

    /**
     * The weight of the product.
     *
     * @var float
     */
    public $weight;

    /**
     * The options for this cart item.
     *
     * @var CartItemOptions
     */
    public $options;

    /**
     * The tax rate for the cart item.
     *
     * @var int|float
     */
    public $taxRate = 0;

    /**
     * @var bool
     */
    public $taxIncluded = false;

    /**
     * The FQN of the associated model.
     *
     * @var string|null
     */
    private $associatedModel = null;

    /**
     * The discount rate for the cart item.
     *
     * @var float
     */
    private $discountRate = 0;

    /**
     * @var bool
     */
    private $percentageDiscount = false;

    /**
     * @var bool
     */
    private $discountApplyOnce = false;

    /**
     * @var CartCoupon
     */
    private $coupon;

    protected $vouchers = [];

    /**
     * @var Memberable
     */
    private $memberable;

    /**
     * The discount rate for the cart item.
     *
     * @var ?float
     */
    private $memberDiscountRate = null;

    /**
     * @var bool
     */
    private $memberPercentageDiscount = false;

    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param float      $weight
     * @param array      $options
     */
    public function __construct($id, $name, $price, $weight = 0, array $options = [])
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }
        if (strlen($price) < 0 || !is_numeric($price)) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }
        if (strlen($weight) < 0 || !is_numeric($weight)) {
            throw new \InvalidArgumentException('Please supply a valid weight.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->price = floatval($price);
        $this->weight = floatval($weight);
        $this->options = new CartItemOptions($options);
        $this->rowId = $this->generateRowId($id, $options);
    }

    /**
     * Returns the formatted weight.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function weight($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->weight, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted price without TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function price($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->price, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted price with discount applied.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function priceTarget($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->priceTarget, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted price with TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function priceTax($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->priceTax, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function subtotal($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->subtotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function total($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->total, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function tax($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->tax, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function taxTotal($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->taxTotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted discount.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function discount($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->discount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted total discount for this cart item.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function discountTotal($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->discountTotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted total price for this cart item.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function priceTotal($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->priceTotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty) || !is_numeric($qty)) {
            throw new \InvalidArgumentException('Please supply a valid quantity.');
        }

        $this->qty = $qty;
    }

    /**
     * Attach coupon to current item
     *
     * @param Couponable $coupon
     */
    public function setCoupon(Couponable $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * Unset coupon
     */
    public function forgetCoupon()
    {
        $this->coupon = null;
    }

    /**
     * Remove discount and coupon attached
     */
    public function removeCoupon()
    {
        $this->discountAmount = 0;
        $this->discountRate = 0;

        $this->coupon = null;
    }

    /**
     * Apply member discount to item
     *
     * @param float $amount
     * @param bool $percentageDiscount
     * @param bool $applyOnce
     * @throws CouponException
     */
    protected function setMemberDiscount($amount, $percentageDiscount = false)
    {
        $this->memberPercentageDiscount = $percentageDiscount;

        if ($this->memberPercentageDiscount && ($amount < 0 || $amount > 100))
            throw new CouponException('Invalid value for a percentage discount. The value must be between 1 and 100.');

        $this->memberDiscountRate = $amount;
    }

    /**
     * Attach member to current item
     *
     * @param Memberable $memberable
     * @param float $amount
     * @param bool $percentageDiscount
     */
    public function setMember(Memberable $memberable, $amount, $percentageDiscount = false)
    {
        $this->setMemberDiscount($amount, $percentageDiscount);

        $this->memberable = $memberable;
    }

    /**
     * Remove discount and coupon attached
     */
    public function removeMember()
    {
        $this->memberDiscountRate = null;

        $this->memberable = null;
    }

    public function applyVoucher(Voucherable $voucher)
    {
        $this->vouchers[] = $voucher;
    }

    public function removeVoucher($voucherCode)
    {
        $this->vouchers = array_filter($this->vouchers, function (Voucherable $voucher) use ($voucherCode) {
            return $voucher->getCode() !== $voucherCode;
        });
    }

    /**
     * @return array|Voucherable[]
     */
    public function getVouchers()
    {
        return $this->vouchers;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param \Gloudemans\Shoppingcart\Contracts\Buyable $item
     *
     * @return void
     */
    public function updateFromBuyable(Buyable $item)
    {
        $this->id = $item->getBuyableIdentifier($this->options);
        $this->name = $item->getBuyableDescription($this->options);
        $this->price = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     *
     * @return void
     */
    public function updateFromArray(array $attributes)
    {
        $this->id = Arr::get($attributes, 'id', $this->id);
        $this->qty = Arr::get($attributes, 'qty', $this->qty);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->weight = Arr::get($attributes, 'weight', $this->weight);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function associate($model)
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param int|float $taxRate
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function setTaxRate($taxRate)
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * @return float|int
     */
    public function getTaxRate()
    {
        return $this->taxRate;
    }

    /**
     * Apply discount to item
     *
     * @param float $amount
     * @param bool $percentageDiscount
     * @param bool $applyOnce
     * @throws CouponException
     */
    public function setDiscount($amount, $percentageDiscount = false, $applyOnce = false)
    {
        $this->percentageDiscount = $percentageDiscount;

        if ($this->percentageDiscount && ($amount < 0 || $amount > 100))
            throw new CouponException('Invalid value for a percentage discount. The value must be between 1 and 100.');

        $this->discountApplyOnce = $applyOnce;

        $this->discountRate = $amount;
    }

    /**
     * Get all vouchers discount amount
     *
     * @return float
     */
    public function getVouchersDiscountAmount()
    {
        return array_reduce($this->vouchers, function ($carry, $voucher) {
            if ($voucher->isPercentage()) {
                $discountPerItem = $this->priceMember * ($voucher->getDiscountValue() / 100);
            } else {
                $discountPerItem = $voucher->getDiscountValue();
            }

            return $carry + ($discountPerItem * $voucher->getDiscountQuantity());
        }, 0);
    }

    /**
     * Get voucher discount amount before multiple with apply quantity
     *
     * @param string $voucherCode
     *
     * @return float|int
     */
    public function getVoucherDiscountAmount($voucherCode)
    {
        foreach ($this->vouchers as $voucher) {
            if ($voucher->getCode() === $voucherCode) {
                if ($voucher->isPercentage()) {
                    return $this->priceMember * ($voucher->getDiscountValue() / 100);
                }

                return $voucher->getDiscountValue();
            }
        }

        return 0;
    }

    /**
     * Get single voucher discount amount
     * @param string $voucherCode
     * @return float
     */
    public function getVoucherTotalDiscountAmount($voucherCode)
    {
        foreach ($this->vouchers as $voucher) {
            if ($voucher->getCode() === $voucherCode) {
                $discountPerItem = $voucher->isPercentage()
                    ? $this->priceMember * ($voucher->getDiscountValue() / 100)
                    : $voucher->getDiscountValue();

                return $discountPerItem * $voucher->getDiscountQuantity();
            }
        }

        return 0;
    }

    /**
     * Get total quantity from all vouchers
     *
     * @return float|int
     */
    public function getVouchersTotalDiscountQuantity()
    {
        return array_sum(array_map(function (Voucherable $v) {
            return $v->getDiscountQuantity();
        }, $this->vouchers));
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     *
     * @return mixed
     */
    public function __get($attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }
        $decimals = config('cart.format.decimals', 2);

        switch ($attribute) {
            case 'model':
                if (isset($this->associatedModel)) {
                    return with(new $this->associatedModel())->find($this->id);
                }
            // no break
            case 'modelFQCN':
                if (isset($this->associatedModel)) {
                    return $this->associatedModel;
                }
            // no break
            case 'weightTotal':
                return round($this->weight * $this->qty, $decimals);
            case 'coupon':
                return ($this->coupon) ? $this->coupon : null;
        }

        //todo:ignore gross price temporary
        if (config('cart.gross_price')) {
            switch ($attribute) {
                case 'priceNet':
                    return round($this->price / (1 + ($this->taxRate / 100)), $decimals);
                case 'discount':
                    return $this->priceNet * ($this->discountRate / 100);
                case 'memberDiscount':
                    return $this->priceNet * ($this->memberDiscountRate / 100);
                case 'tax':
                    return round($this->priceTarget * ($this->taxRate / 100), $decimals);
                case 'priceTax':
                    return round($this->priceTarget + $this->tax, $decimals);
                case 'discountTotal':
                    return round($this->discount * $this->qty, $decimals) + $this->memberDiscountTotal;
                case 'memberDiscountTotal':
                    return round($this->memberDiscount * $this->qty, $decimals);
                case 'voucherDiscountTotal':
                    return $this->getVouchersDiscountAmount();
                case 'allDiscountTotal':
                    return $this->discountTotal + $this->memberDiscountTotal + $this->voucherDiscountTotal;
                case 'priceTotal':
                    return round($this->priceNet * $this->qty, $decimals);
                case 'subtotal':
                    return round($this->priceTotal - $this->discountTotal, $decimals);
                case 'priceTarget':
                    return round(($this->priceTotal - $this->discountTotal) / $this->qty, $decimals);
                case 'taxTotal':
                    return round($this->subtotal * ($this->taxRate / 100), $decimals);
                case 'total':
                    return round($this->subtotal + $this->taxTotal, $decimals);
                default:
                    return;
            }
        } else {
            switch ($attribute) {
                case 'discount':
                    return ($this->percentageDiscount) ? (($this->price - $this->memberDiscount) * ($this->discountRate / 100)) : min($this->price - $this->memberDiscount, $this->discountRate);
                case 'memberDiscount':
                    return ($this->memberPercentageDiscount) ? ($this->price * ($this->memberDiscountRate / 100)) : $this->memberDiscountRate;
                case 'tax':
                    $amount = ($this->taxIncluded) ?
                        calc_tax_amount($this->priceTarget, $this->taxRate, $this->taxIncluded) :
                        $this->priceTarget * ($this->taxRate / 100);

                    return round($amount, $decimals);
                case 'taxTotal':
                    return round($this->subtotal * ($this->taxRate / 100), $decimals);
                case 'taxed': // use taxTotal above which coming from upstream
                    return $this->tax * $this->qty;
                case 'taxable':
                    return ($this->taxIncluded) ? $this->total - $this->taxTotal : $this->subtotal;
                case 'priceTax':
                    $amount = ($this->taxIncluded) ? $this->priceTarget : $this->priceTarget + $this->tax;
                    return round($amount, $decimals);
                case 'memberDiscountTotal':
                    return round($this->memberDiscount * $this->qty, $decimals);
                case 'voucherDiscountTotal':
                    return $this->getVouchersDiscountAmount();
                case 'discountTotal':
                    return round($this->discount * ($this->discountApplyOnce ? 1 : $this->qty), $decimals);
                case 'allDiscountTotal':
                    return $this->discountTotal + $this->memberDiscountTotal + $this->voucherDiscountTotal;
                case 'priceTotal':
                    return round($this->price * $this->qty, $decimals);
                case 'subtotalWithoutDiscount':
                    return max(round($this->priceTotal - $this->memberDiscountTotal, $decimals), 0);
                case 'subtotal':
                    return max(round($this->priceTotal - $this->allDiscountTotal, $decimals), 0);
                case 'priceTarget':
                    return max(round(($this->priceTotal - $this->allDiscountTotal) / $this->qty, $decimals), 0);
                case 'priceMember':
                    return max($this->price - $this->memberDiscount, 0);
                case 'total':
                    return round($this->subtotal + $this->taxTotal, $decimals);
                default:
                    return;
            }
        }
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param \Gloudemans\Shoppingcart\Contracts\Buyable $item
     * @param array                                      $options
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public static function fromBuyable(Buyable $item, array $options = [])
    {
        return new self($item->getBuyableIdentifier($options), $item->getBuyableName($options), $item->getBuyablePrice($options), $item->getBuyableWeight($options), $options);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public static function fromArray(array $attributes)
    {
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $attributes['weight'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public static function fromAttributes($id, $name, $price, $weight, array $options = [])
    {
        return new self($id, $name, $price, $weight, $options);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     *
     * @return string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id.serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price,
            'weight'   => $this->weight,
            'options'  => $this->options->toArray(),
            'discount' => $this->discount,
            'tax'      => $this->tax,
            'subtotal' => $this->subtotal,
            'coupon'   => optional($this->coupon)->toArray(),
            'memberDiscount' => $this->memberDiscount,
            'memberable' => optional($this->memberable)->toArray(),
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
    private function numberFormat($value, $decimals, $decimalPoint, $thousandSeperator)
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

    public function duplicate($newId = null)
    {
        if (is_null($newId))
            $newId = $this->id;

        $clone = clone $this;

        $clone->rowId = $clone->generateRowId($newId, $this->options->all());

        return $clone;
    }
}

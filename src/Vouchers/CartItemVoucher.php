<?php

namespace Gloudemans\Shoppingcart\Vouchers;

use Carbon\Carbon;
use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Contracts\Voucherable;
use Gloudemans\Shoppingcart\Contracts\VoucherDiscountable;
use Gloudemans\Shoppingcart\Exceptions\NoEligibleItemVoucherException;
use Gloudemans\Shoppingcart\Exceptions\VoucherException;
use Illuminate\Support\Collection;

/**
 *
 * @property-read VoucherDiscountable $discountable
 */
class CartItemVoucher implements Voucherable
{
    protected $code;

    protected $value;

    /**
     * @var float
     */
    protected $discountValue;

    protected $type;

    /**
     * The options for this coupon.
     *
     * @var array
     */
    protected $options;

    /**
     * @var Carbon
     */
    protected $dateFrom;

    /**
     * @var Carbon
     */
    protected $dateTo;

    /**
     * The primary key of FQN  of associated discountable
     *
     * @var int
     */
    protected $discountableId = null;

    /**
     * The FQN of the associated discountable.
     *
     * @var string|null
     */
    protected $discoutableClass = null;

    /**
     * @var bool
     */
    protected $applyOnce;

    /**
     * @var integer|float|null
     */
    protected $applyQuantity;

    /**
     * @var int|float
     */
    protected $discountQuantity;

    /**
     * @var CouponDiscountable
     */
    protected $discountableModel;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var false|mixed
     */
    protected $percentageDiscount;

    public function __construct(
        $id,
        $code,
        $value,
        $type,
        VoucherDiscountable $discountable,
        Carbon $dateFrom = null,
        Carbon $dateTo = null,
        $percentageDiscount = false,
        $applyQuantity = null,
        $options = []
    ) {
        $this->id = $id;

        $this->type = $type;

        $this->code = $code;

        $this->value = $value;

        $this->discountValue = $value;

        $this->percentageDiscount = $percentageDiscount;

        $this->dateFrom = $dateFrom;

        $this->dateTo = $dateTo;

        $this->options = new Collection($options);

        $this->applyQuantity = $applyQuantity;

        $this->applyOnce = $this->applyQuantity == 1 ? true : false;

        $this->associate($discountable);
    }

    /**
     * Apply current voucher discount to cart or cart items that will change cart or cart items amount permanently
     *
     * @param Cart $cart
     * @return float
     * @throws VoucherException
     */
    public function apply(Cart $cart, $throwErrors = true)
    {
//        $requiredAmount = $this->discountable->getMinRequiredAmount();
//
//        if (!is_null($requiredAmount)) {
//            $this->checkMinAmount($cart, $requiredAmount);
//        }
        $discountableCartItems = $this->getDiscountableCartItems($cart);

        $totalCartItemsAmount = $discountableCartItems->sum(function(CartItem $item) {
            return $item->total;
        });

        if ($discountableCartItems->isEmpty() && $throwErrors) {
            throw new VoucherException("Your cart does not contain items from " . $this->discountable->getVoucherDiscountableDescription());
        }

        $appliedCartItems = [];
        $appliedVariantsQuantity = [];

        if ($discountableCartItems->isNotEmpty()) {
            if (!$this->percentageDiscount && $this->applyOnce) {
//                $lowestPriceItem = $discountableCartItems->where('price', $discountableCartItems->min('price'))->first();
//
//                $this->setDiscountOnItem($lowestPriceItem);

                // we split the total discount amount
                $decimals = config('cart.format.decimals', 2);

                //TODO: Add logic to allow discount by fixed amount and divided among all eligible items
                foreach ($discountableCartItems as $cartItem) {
//                    $this->applyToCart = false;

                    $this->discountValue = round($cartItem->price / $totalCartItemsAmount * $this->value, $decimals);

                    $appliedCartItems[] = $cart->applyVoucherToItem($cartItem->rowId, $this);
                }
            } else {
                foreach ($discountableCartItems as $cartItem) {
                    $totalAppliedVariantsQuanttity = collect($appliedVariantsQuantity)->sum();

                    if ($totalAppliedVariantsQuanttity < $this->getApplyQuantity()) {
                        $appliedCartItem = $cart->applyVoucherToItem($cartItem->rowId, $this);

                        if ($appliedCartItem) {
                            $appliedVariantsQuantity[$cartItem->id] = ($appliedVariantsQuantity[$cartItem->id] ?? 0) + $this->getDiscountQuantity();

                            $appliedCartItems[] = $appliedCartItem;
                        }
                    }
                }
            }
        }

        if (collect($appliedCartItems)->filter()->isEmpty() && $throwErrors) {
            throw new NoEligibleItemVoucherException();
        }
    }

//    /**
//     * Forget current coupon discount to cart or cart items that revert changes to cart or cart items amount
//     *
//     * @param Cart $cart
//     * @throws VoucherException
//     */
//    public function forget(Cart $cart, $throwErrors = true)
//    {
//        $discountableCartItems = $this->getDiscountableCartItems($cart);
//
//        if ($discountableCartItems->isNotEmpty()) {
////            if ($this->applyOnce) {
////                $lowestPriceItem = $discountableCartItems->where('price', $discountableCartItems->min('price'))->first();
////
////                $this->removeDiscountOnItem($lowestPriceItem);
////            } else {
//                foreach ($discountableCartItems as $cartItem) {
//                    if ($cartItem->coupon->code == $this->getCode()) {
//                        $this->removeDiscountOnItem($cartItem);
//                    }
//                }
////            }
//        }
//    }

    /**
     * Gets the total discount amount.
     *
     * @param Cart $cart
     * @param $throwErrors boolean this allows us to capture errors in our code if we wish,
     * that way we can spit out why the coupon has failed
     *
     * @return float
     */
    public function discount(Cart $cart, $throwErrors = true)
    {
//        return $cart->items()->reduce(function ($total, CartItem $cartItem) {
//            return $total + (($cartItem->coupon == $this) ? $cartItem->discountTotal : 0);
//        }, 0);

        return $cart->getVoucherDiscountAmount($this->getCode());
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'discountable' => $this->discountable,
            'apply_once' => $this->applyOnce,
            'apply_quantity' => $this->applyQuantity,
            'percentage_discount' => $this->percentageDiscount,
            'options' => $this->options->toArray(),
        ];
    }

    /**
     * Get list of cart items that are eligible for discount
     *
     * @param Cart $cart
     * @return \Illuminate\Support\Collection|CartItem[]
     */
    protected function getDiscountableCartItems(Cart $cart)
    {
        $discountableIds = $this->discountable->getVoucherDiscountableIdentifiers();

        // if discountable is keyword * which means we discount every item in cart
        if ($discountableIds->count() == 1 && $discountableIds->first() == '*')
            return $cart->items();

        return $cart->search(function (CartItem $cartItem) {
            return in_array($cartItem->id, $this->discountable->getVoucherDiscountableIdentifiers()->all());
        });
    }

    /**
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = [])
    {
        $str = $this->applyQuantity + 0 . ' x ' . $this->discountable->getVoucherDiscountableDescription();

        return $str;
    }

    /**
     * Associate the voucher discountable with the given model.
     *
     * @param mixed $discountable
     *
     * @return CartItemVoucher
     */
    public function associate($discountable)
    {
        $this->discoutableClass = is_string($discountable) ? $discountable : get_class($discountable);
        $this->discountableId = $discountable->id;
        $this->discountableModel = $discountable;

        return $this;
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

        switch ($attribute) {
            case 'discountable':
                if (!$this->discountableModel && isset($this->discoutableClass)) {
                    $this->discountableModel = with(new $this->discoutableClass)->find($this->discountableId);
                }

                return $this->discountableModel;
                break;
            case 'discountableFQCN':
                if (isset($this->discoutableClass)) {
                    return $this->discoutableClass;
                }
                break;
            default:
        }
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function isPercentage()
    {
        return $this->percentageDiscount;
    }

    /**
     * @inheritDoc
     */
    public function getDiscountValue()
    {
        return $this->discountValue;
    }

    /**
     * @inheritDoc
     */
    public function getApplyQuantity()
    {
        return $this->applyQuantity;
    }

    /**
     * @inheritDoc
     */
    public function getDiscountQuantity()
    {
        return $this->discountQuantity;
    }

    /**
     * @inheritDoc
     */
    public function setDiscountQuantity($quantity)
    {
        $this->discountQuantity = $quantity;
    }

    /**
     * @inheritDoc
     */
    public function isApplyToCart()
    {
        return false;
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
}

<?php

namespace Gloudemans\Shoppingcart;

use Closure;
use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Contracts\Couponable;
use Gloudemans\Shoppingcart\Contracts\InstanceIdentifier;
use Gloudemans\Shoppingcart\Exceptions\CartAlreadyStoredException;
use Gloudemans\Shoppingcart\Exceptions\CouponException;
use Gloudemans\Shoppingcart\Exceptions\CouponNotFoundException;
use Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException;
use Gloudemans\Shoppingcart\Exceptions\UnknownModelException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Cart
{
    const DEFAULT_INSTANCE = 'default';

    /**
     * Instance of the session manager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    private $session;

    /**
     * Instance of the event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $events;

    /**
     * Holds the current cart instance.
     *
     * @var string
     */
    private $instance;

    /**
     * Defines the discount percentage.
     *
     * @var float
     */
    private $discount = 0;

    /**
     * Defines the discount percentage.
     *
     * @var float
     */
    private $taxRate = 0;

    /**
     * Cart constructor.
     *
     * @param \Illuminate\Session\SessionManager $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;
        $this->taxRate = config('cart.tax');

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param string|null $instance
     *
     * @return \Gloudemans\Shoppingcart\Cart
     */
    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        if ($instance instanceof InstanceIdentifier) {
            $this->discount = $instance->getInstanceGlobalDiscount();
            $instance = $instance->getInstanceIdentifier();
        }

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance()
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item to the cart.
     *
     * @param mixed $id
     * @param mixed $name
     * @param int|float $qty
     * @param float $price
     * @param float $weight
     * @param array $options
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function add($id, $name = null, $qty = null, $price = null, $weight = 0, array $options = [])
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $cartItem = $this->createCartItem($id, $name, $qty, $price, $weight, $options);

        return $this->addCartItem($cartItem);
    }

    /**
     * Add an item to the cart.
     *
     * @param \Gloudemans\Shoppingcart\CartItem $item Item to add to the Cart
     * @param bool $keepDiscount Keep the discount rate of the Item
     * @param bool $keepTax Keep the Tax rate of the Item
     *
     * @return \Gloudemans\Shoppingcart\CartItem The CartItem
     */
    public function addCartItem($item, $keepDiscount = false, $keepTax = false)
    {
//        if (!$keepDiscount) {
//            $item->setDiscount($this->discount);
//        }

//        if (!$keepTax) {
//            $item->setTaxRate($this->taxRate);
//        }

        $items = $this->getItems();

        $newItem = false;

        if ($items->has($item->rowId)) {
            $item->qty += $items->get($item->rowId)->qty;
        } else {
            $newItem = true;
        }

        $items->put($item->rowId, $item);

        $this->events->dispatch('cart.added', $item);

        $this->session->put($this->instance, $this->getContent()->put('items', $items));

////        if ($newItem) {
//            // reapply coupons
//            $coupons = $this->allCoupons();
//
//            foreach ($coupons as $coupon) {
//                $coupon->apply($this);
//            }
////        }

        return $item;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed $qty
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function update($rowId, $qty)
    {
        $cartItem = $this->get($rowId);

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = $qty;
        }

        $items = $this->getItems();

        if ($rowId !== $cartItem->rowId) {
            $items->pull($rowId);

            if ($items->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);

            return;
        } else {
            $items->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.updated', $cartItem);

        $this->session->put($this->instance, $this->getContent()->put('items', $items));

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     *
     * @return void
     */
    public function remove($rowId)
    {
        $cartItem = $this->get($rowId);

        $items = $this->getItems();

        $items->pull($cartItem->rowId);

        // Validate all coupons and remove coupon that is applied to this cart item
        $this->validateCoupons();

        $this->events->dispatch('cart.removed', $cartItem);

        $this->session->put($this->instance, $this->getContent()->put('items', $items));
    }

    /**
     * Validate all coupons and remove coupon that is not eligible anymore e.g: after cart item is removed
     */
    protected function validateCoupons()
    {
        foreach ($this->coupons() as $coupon) {
            try {
                $coupon->discount($this);
            } catch (CouponException $e) {
                $this->removeCoupon($coupon->getCode());
            }
        }
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function get($rowId)
    {
        $items = $this->getItems();

        if (!$items->has($rowId)) {
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");
        }

        return $items->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the cart.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content()
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    /**
     * Get items of the cart
     *
     * @return \Illuminate\Support\Collection
     */
    public function items()
    {
        return $this->getContent()->get('items', new Collection());
    }

    /**
     * Get attributes of the cart
     *
     * @return \Illuminate\Support\Collection
     */
    public function attributes()
    {
        return $this->getContent()->get('attributes', new Collection());
    }

    /**
     * Get coupons of the cart
     *
     * @return \Illuminate\Support\Collection|\Gloudemans\Shoppingcart\Contracts\Couponable[]
     */
    public function coupons()
    {
        return $this->getContent()->get('coupons', new Collection());
    }

    /**
     * Get all coupons from coupons collection and item coupons
     *
     * @return Collection
     */
    public function allCoupons()
    {
        $coupons = $this->coupons();

        $cartItemCoupons = $this->items()->filter(function (CartItem $cartItem) {
            return $cartItem->coupon;
        })->map(function (CartItem $cartItem) {
            return $cartItem->coupon;
        })->unique(function (Couponable $couponable) {
            return $couponable->getCode();
        })->keyBy(function (Couponable $couponable) {
            return $couponable->getCode();
        });

        return $coupons->merge($cartItemCoupons);
    }

    /**
     * Add a new attribute or replace if existing key is found
     *
     * @param string|array $keys
     * @param mixed $value
     * @return bool
     */
    public function addAttribute($keys, $value)
    {
        if (!is_array($keys)) {
            $keys = [$keys => $value];
        }

        $attributes = $this->attributes();

        foreach ($keys as $key => $value) {
            $attributes->put($key, $value);
        }

        // if this is an shipping attribute, we validate all coupons again just in case there is a shipping coupon
        // that depends on shipping country is changed
        if (in_array(config('cart.session.shipping'), array_keys($keys))) {
            $this->validateCoupons();
        }

        $this->events->dispatch('cart.attribute_added', $keys);

        $this->session->put($this->instance, $this->getContent()->put('attributes', $attributes));

        return true;
    }

    /**
     * Get a cart attribute from the cart by its key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        $attributes = $this->attributes();

//        if (!$attributes->has($key)) {
//            throw new InvalidAttributeException("The cart does not contain attribute {$key}.");
//        }

        return $attributes->get($key, $default);
    }

    /**
     * Remove the cart attribute with the given key from the cart.
     *
     * @param string $key
     *
     * @return void
     */
    public function removeAttribute($key)
    {
        $attributes = $this->attributes();

        if ($attributes->has($key)) {
            $attribute = $attributes->pull($key);

            $this->events->dispatch('cart.attribute_removed', $attribute);

            $this->session->put($this->instance, $this->getContent()->put('attributes', $attributes));
        }
    }

    /**
     * Get fees of the cart
     *
     * @return \Illuminate\Support\Collection|CartFee[]
     */
    public function fees()
    {
        return $this->getContent()->get('fees', new Collection());
    }

    /**
     * @param string $rowId
     * @return CartFee
     */
    public function getFee($rowId)
    {
        $fees = $this->fees();

        if (!$fees->has($rowId)) {
            throw new InvalidRowIDException("The cart fees does not contain rowId {$rowId}.");
        }

        return $fees->get($rowId);
    }

    /**
     * @param $id
     * @param null $type
     * @param null $name
     * @param null $qty
     * @param null $price
     * @param string $description
     * @param int $weight
     * @param array $options
     * @return array|CartItem
     */
    public function addFee(
        $id,
        $type = null,
        $name = null,
        $qty = null,
        $price = null,
        $description = '',
        $weight = 0,
        array $options = []
    ) {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $cartFee = $this->createCartFee($id, $type, $name, $qty, $price, $description, $weight, $options);

        return $this->addCartFee($cartFee);
    }

    /**
     * @param $fee CartFee
     * @param bool $keepDiscount
     * @param bool $keepTax
     * @return CartFee
     */
    public function addCartFee($fee, $keepDiscount = false, $keepTax = false)
    {
        if (!$keepDiscount) {
            $fee->setDiscount($this->discount);
        }

        if (!$keepTax) {
            $fee->setTaxRate($this->taxRate);
        }

        $fees = $this->fees();

//        if ($fees->has($fee->rowId)) {
//            $fee->qty += $fees->get($fee->rowId)->qty;
//        }

        $fees->put($fee->rowId, $fee);

        $this->events->dispatch('cart.fee_added', $fee);

        $this->session->put($this->instance, $this->getContent()->put('fees', $fees));

        return $fee;
    }

    /**
     * @param $fee CartFee
     * @param bool $keepDiscount
     * @param bool $keepTax
     * @return mixed
     */
    private function createCartFee($id, $type, $name, $qty, $price, $description = null, $weight = 0, array $options = [])
    {
        if (is_array($id)) {
            if (empty($id['id'])) {
                $id['id'] = $id['type'];
            }

            if (empty($id['qty'])) {
                $id['qty'] = 1;
            }

            $cartFee = CartFee::fromArray($id);
            $cartFee->setQuantity($id['qty']);

        } else {
            if (empty($id)) {
                $id = $type;
            }

            $cartFee = CartFee::fromParameters($id, $type, $name, $price, $description, $weight, $options);
            $cartFee->setQuantity($qty);
        }

        return $cartFee;
    }

    /**
     * Update fee
     * @param $rowId
     * @param $qty
     * @return CartFee|void
     */
    public function updateFee($rowId, $qty)
    {
        $cartFee = $this->getFee($rowId);

        if (is_array($qty)) {
            $cartFee->updateFromArray($qty);
        } else {
            $cartFee->qty = $qty;
        }

        $fees = $this->fees();

        if ($rowId !== $cartFee->rowId) {
            $fees->pull($rowId);

            if ($fees->has($cartFee->rowId)) {
                $existingCartItem = $this->getFee($cartFee->rowId);
                $cartFee->setQuantity($existingCartItem->qty + $cartFee->qty);
            }
        }

        if ($cartFee->qty <= 0) {
            $this->removeFee($cartFee->rowId);

            return;
        } else {
            $fees->put($cartFee->rowId, $cartFee);
        }

        $this->events->dispatch('cart.fee_updated', $cartFee);

        $this->session->put($this->instance, $this->getContent()->put('fees', $fees));

        return $cartFee;
    }

    /**
     * Remove the cart fee with the given rowId from the cart.
     *
     * @param string $rowId
     *
     * @return void
     */
    public function removeFee($rowId)
    {
        $cartFee = $this->getFee($rowId);

        $fees = $this->fees();

        $fees->pull($cartFee->rowId);

        $this->events->dispatch('cart.fee_removed', $cartFee);

        $this->session->put($this->instance, $this->getContent()->put('fees', $fees));
    }

    /**
     * Applies a coupon to the cart and throw exception if requirments is not met
     *
     * @param Couponable $coupon
     * @return Couponable
     * @throws CouponException
     */
    public function addCoupon(Couponable $coupon)
    {
        if (!config('cart.allow_multiple_same_type_discount')) {
            $sameTypeCoupons = $this->coupons()->filter(function (Couponable $item) use ($coupon) {
                return $item->getType() == $coupon->getType();
            });

            $sameItemTypeCoupons = $this->items()->filter(function (CartItem $item) use ($coupon) {
                return ($item->coupon && $item->coupon->getType() == $coupon->getType());
            });

            if ($sameTypeCoupons->count() + $sameItemTypeCoupons->count() > 0) {
                throw new CouponException('Multiple ' . Str::plural(config('cart.discount.coupon_label')) . ' of same type cannot be applied.');
            }
        }

        if ($this->totalFloat() <= 0) {
            throw new CouponException('Cannot further discount on your cart total.');
        }

        $coupon->apply($this);

        if ($coupon->isApplyToCart() && $coupon->discount($this, false)) {
            $coupons = $this->coupons();

            $coupons->put($coupon->getCode(), $coupon);

            $this->session->put($this->instance, $this->getContent()->put('coupons', $coupons));
        }

        $this->events->dispatch('cart.coupon_added', $coupon);

        return $coupon;
    }

    /**
     * Remove coupon with the given code from the cart.
     *
     * @param string $code
     *
     * @return void
     */
    public function removeCoupon($code)
    {
        $code = urldecode($code);

        $coupon = $this->getCoupon($code);

        $coupon->forget($this);

        $coupons = $this->coupons();

        $coupons->pull($coupon->getCode());

        $this->events->dispatch('cart.coupon_removed', $coupon);

        $this->session->put($this->instance, $this->getContent()->put('coupons', $coupons));
    }

    /**
     * Get coupon from coupons collection and cart items coupons
     *
     * @param string $code
     * @return Couponable
     */
    public function getCoupon($code)
    {
        $coupons = $this->allCoupons();

        if (!$coupons->has($code)) {
            throw new CouponNotFoundException(sprintf("The %s  %s is not found.", config('cart.discount.coupon_label'), $code));
        }

        return $coupons->get($code);
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count()
    {
        return $this->getItems()->sum('qty');
    }

    /**
     * Get the number of items instances in the cart.
     *
     * @return int|float
     */
    public function countInstances()
    {
        return $this->getContent()->count();
    }

    /**
     * Get total items amount optionally with tax
     *
     * @param bool $withTax
     * @return float
     */
    public function itemsTotal($withTax = false)
    {
        $items = $this->items();

        $total = $items->reduce(function ($total, CartItem $cartItem) use ($withTax) {
            return $total + ($withTax ? $cartItem->total : $cartItem->subtotal);
        }, 0);

        return $total;
    }

    /**
     * Get total fees amount optionally with tax
     *
     * @param bool $withTax
     * @return float
     */
    public function feesTotal($withTax = false)
    {
        $fees = $this->fees();

        return $this->getFeesTotal($fees, $withTax);
    }

    /**
     * Get total fees amount by type optionally with tax
     *
     * @param string $feeType
     * @param bool $withTax
     * @return float
     */
    public function feesTypeTotal($feeType, $withTax = true)
    {
        $fees = $this->searchFee(function (CartFee $fee) use ($feeType) {
            return $fee->type == $feeType;
        });

        return $this->getFeesTotal($fees, $withTax);
    }

    protected function getFeesTotal(Collection $fees, $withTax = false)
    {
        $total = $fees->reduce(function ($total, CartFee $cartFee) use ($withTax) {
            return $total + ($withTax ? $cartFee->total : $cartFee->subtotal);
        }, 0);

        return $total;
    }

    /**
     * Get all discount amount in items
     *
     * @return float
     */
    public function itemsDiscountFloat()
    {
        return $this->items()->reduce(function ($total, CartItem $cartItem) {
            return $total + $cartItem->discountTotal;
        }, 0);
    }

    /**
     * @param bool $withItemDiscounts
     * @return float
     */
    public function discountsTotal($withItemDiscounts = false)
    {
        $total = 0;

        if ($withItemDiscounts) {
            $total += $this->itemsDiscountFloat();
        }

        foreach ($this->coupons() as $coupon) {
//            if ($coupon->appliedToCart) {
            $total += $coupon->discount($this);
//            }
        }

        return $total;
    }

    /**
     * Get total discountable amount
     *
     * @return float
     */
    public function getDiscountableFloat(Couponable $excludeCoupon = null)
    {
        $subTotal = $this->subtotalFloat();

        if (config('cart.discount.discount_on_fees', false)) {
            $subTotal += $this->feesTotal();
        }

        return $subTotal;
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @return float
     */
    public function totalFloat($roundNegative = false)
    {
        $total = $this->itemsTotal(true) + $this->itemShippingsFloat() + $this->feesTotal(true) - $this->discountsTotal();

        return $roundNegative ? max($total, 0) : $total;
    }

    /**
     * Get the total price of the items in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function total($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->totalFloat(true), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @return float
     */
    public function taxFloat()
    {
        return $this->getItems()->reduce(function ($tax, CartItem $cartItem) {
            return $tax + $cartItem->taxTotal;
        }, 0);
    }

    /**
     * Get the total tax of the items in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function tax($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->taxFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return float
     */
    public function subtotalFloat()
    {
        return $this->getItems()->reduce(function ($subTotal, CartItem $cartItem) {
            return $subTotal + $cartItem->subtotal;
        }, 0);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function subtotal($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->subtotalFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return float
     */
    public function discountFloat()
    {
        return $this->getItems()->reduce(function ($discount, CartItem $cartItem) {
            return $discount + $cartItem->discountTotal;
        }, 0);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function discount($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->discountFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the total shipping cost in items collection
     *
     * @return float
     */
    public function itemShippingFloat()
    {
        return $this->getItems()->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->qty * $cartItem->options->get('shipping_cost'));
        }, 0);
    }

    /**
     * Get the total shipping fees in the cart.
     *
     * @return float
     */
    public function shippingFloat()
    {
        return $this->itemShippingsFloat() + $this->feesTypeTotal(config('cart.shipping_type_id'));
    }

    /**
     * Get the total shipping fees in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function shipping($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->shippingFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return float
     */
    public function initialFloat()
    {
        return $this->getItems()->reduce(function ($initial, CartItem $cartItem) {
            return $initial + ($cartItem->qty * $cartItem->price);
        }, 0);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function initial($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->initialFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the total weight of the items in the cart.
     *
     * @return float
     */
    public function weightFloat()
    {
        return $this->getItems()->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->qty * $cartItem->weight);
        }, 0);
    }

    /**
     * Get the total weight of the items in the cart.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function weight($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->weightFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the shipping cost of the items in the cart.
     *
     * @return float
     */
    public function itemShippingsFloat()
    {
        return $this->getItems()->reduce(function ($shipping, CartItem $cartItem) {
            return $shipping + ($cartItem->qty * $cartItem->options->get('shipping_cost', 0));
        }, 0);
    }

    /**
     * Get the shipping cost of the items in the cart as formatted string.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     *
     * @return string
     */
    public function itemShippings($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        return $this->numberFormat($this->itemShippingsFloat(), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     *
     * @return \Illuminate\Support\Collection
     */
    public function search(Closure $search)
    {
        return $this->getItems()->filter($search);
    }

    /**
     * Search the cart content for a cart fee matching the given search closure.
     *
     * @param Closure $search
     * @return Collection
     */
    public function searchFee(Closure $search)
    {
        return $this->fees()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed $model
     *
     * @return void
     */
    public function associate($rowId, $model)
    {
        if (is_string($model) && !class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $items = $this->getItems();

        $items->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $this->getContent()->put('items', $items));
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param string $rowId
     * @param int|float $taxRate
     *
     * @return void
     */
    public function setTax($rowId, $taxRate)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $items = $this->getItems();

        $items->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $this->getContent()->put('items', $items));
    }

    /**
     * Set the global tax rate for the cart.
     * This will set the tax rate for all items.
     *
     * @param float $discount
     */
    public function setGlobalTax($taxRate)
    {
        $this->taxRate = $taxRate;

        $items = $this->getItems();
        if ($items && $items->count()) {
            $items->each(function ($item, $key) {
                $item->setTaxRate($this->taxRate);
            });
        }
    }

    /**
     * Set the discount rate for the cart item with the given rowId.
     *
     * @param string $rowId
     * @param int|float $discount
     * @param bool $percentageDiscount
     * @param bool $applyOnce
     *
     * @return void
     */
    public function setDiscountRate($rowId, $discount, $percentageDiscount = false, $applyOnce = false)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setDiscount($discount, $percentageDiscount, $applyOnce);

        $items = $this->getItems();

        $items->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $this->getContent()->put('items', $items));
    }

    /**
     * Set the global discount percentage for the cart.
     * This will set the discount for all cart items.
     *
     * @param float $discount
     *
     * @return void
     */
    public function setGlobalDiscount($discount, $percentageDiscount = false, $applyOnce = false)
    {
        $this->discount = $discount;

        $items = $this->getItems();
        if ($items && $items->count()) {
            $items->each(function ($item, $key) use ($percentageDiscount, $applyOnce) {
                $item->setDiscount($this->discount, $percentageDiscount, $applyOnce);
            });
        }
    }

    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     *
     * @return void
     */
    public function store($identifier)
    {
        $content = $this->getContent();

        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        if ($this->storedCartWithIdentifierExists($identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $this->currentInstance(),
            'content' => serialize($content),
        ]);

        $this->events->dispatch('cart.stored');
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     *
     * @return void
     */
    public function restore($identifier)
    {
        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        if (!$this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize(data_get($stored, 'content'));

        $currentInstance = $this->currentInstance();

        $this->instance(data_get($stored, 'instance'));

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.restored');

        $this->session->put($this->instance, $content);

        $this->instance($currentInstance);

        $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->delete();
    }

    /**
     * Merges the contents of another cart into this cart.
     *
     * @param mixed $identifier Identifier of the Cart to merge with.
     * @param bool $keepDiscount Keep the discount of the CartItems.
     * @param bool $keepTax Keep the tax of the CartItems.
     *
     * @return bool
     */
    public function merge($identifier, $keepDiscount = false, $keepTax = false)
    {
        if (!$this->storedCartWithIdentifierExists($identifier)) {
            return false;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize($stored->content);

        foreach ($storedContent->get('items') as $cartItem) {
            $this->addCartItem($cartItem, $keepDiscount, $keepTax);
        }

        return true;
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     *
     * @return float|null
     */
    public function __get($attribute)
    {
        switch ($attribute) {
            case 'total':
                return $this->total();
            case 'tax':
                return $this->tax();
            case 'subtotal':
                return $this->subtotal();
            default:
                return;
        }
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getContent()
    {
        if ($this->session->has($this->instance)) {
            return $this->session->get($this->instance);
        }

        return new Collection();
    }

    /**
     * Get carts items
     *
     * @return Collection
     */
    protected function getItems()
    {
//        if ($this->session->has($this->instance)) {
//            return $this->session->get($this->instance . '.items');
//        }
//
//        return new Collection();

        return $this->items();
    }

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param mixed $id
     * @param mixed $name
     * @param int|float $qty
     * @param float $price
     * @param float $weight
     * @param array $options
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    private function createCartItem($id, $name, $qty, $price, $weight, array $options)
    {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id, $qty ?: []);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name, $price, $weight, $options);
            $cartItem->setQuantity($qty);
        }

        return $cartItem;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param mixed $item
     *
     * @return bool
     */
    private function isMulti($item)
    {
        if (!is_array($item)) {
            return false;
        }

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    /**
     * @param $identifier
     *
     * @return bool
     */
    private function storedCartWithIdentifierExists($identifier)
    {
        return $this->getConnection()->table($this->getTableName())->where('identifier', $identifier)->exists();
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    private function getConnection()
    {
        return app(DatabaseManager::class)->connection($this->getConnectionName());
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private function getTableName()
    {
        return config('cart.database.table', 'shoppingcart');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }

    /**
     * Get the Formatted number.
     *
     * @param $value
     * @param $decimals
     * @param $decimalPoint
     * @param $thousandSeperator
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
}

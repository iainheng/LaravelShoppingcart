<?php

namespace Gloudemans\Shoppingcart\Contracts;


use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Exceptions\CouponException;

/**
 * Interface Couponable.
 */
interface Voucherable
{
    /**
     * Get id number of the voucher model
     * 
     * @return int
     */
    public function getId();

    /**
     * Get the discount code
     *
     * @return string
     */
    public function getCode();

    /**
     * Get discount type e.g. order-amount, shipping
     *
     * @return string
     */
    public function getType();

    /**
     * Check if it is a percentage discount
     *
     * @return bool
     */
    public function isPercentage();

    /**
     * @return float
     */
    public function getDiscountValue();

    /**
     * @return int|float
     */
    public function getDiscountQuantity();

    /**
     * Allow to overwrite discount quantity when item remaining quantity is less than voucher quantity
     *
     * @param int|float $quantity
     * @return void
     */
    public function setDiscountQuantity($quantity);

    /**
     * Get the discount requirements and description
     *
     * @param Cart $cart
     * @param array $options
     * @return string
     */
    public function getDescription(Cart $cart = null, $options = []);

    /**
     * Apply current coupon discount to cart or cart items that will change cart or cart items amount permanently
     *
     * @param Cart $cart
     * @param bool $throwErrors
     * @return float
     * @throws CouponException
     */
    public function apply(Cart $cart, $throwErrors = true);

    /**
     * Forget current coupon discount to cart or cart items that revert changes to cart or cart items amount
     *
     * @param Cart $cart
     * @param bool $throwErrors
     * @return float
     * @throws CouponException
     */
//    public function forget(Cart $cart, $throwErrors = true);

    /**
     * Gets the discount amount.
     *
     * @param Cart $cart
     * @return float
     * @throws CouponException
     */
    public function discount(Cart $cart, $throwErrors = true);

    /**
     * Check if coupon discount is apply to cart or items
     *
     * @return bool
     */
    public function isApplyToCart();

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray();

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0);

}

<?php

namespace Gloudemans\Shoppingcart\Contracts;


use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Exceptions\CouponException;

/**
 * Interface Couponable.
 */
interface Couponable
{
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
    public function forget(Cart $cart, $throwErrors = true);

    /**
     * Gets the discount amount.
     *
     * @param Cart $cart
     * @return float
     * @throws CouponException
     */
    public function discount(Cart $cart, $throwErrors = true);

    /**
     * If an item is supplied it will get its discount value.
     *
     * @param CartItem $cartItem
     *
     * @return mixed
     */
    public function forItem(CartItem $cartItem);

    /**
     * Check if coupon discount is apply to cart or items
     *
     * @return bool
     */
    public function isApplyToCart();

    /**
     * Displays the type of value it is for the user.
     *
     * @return mixed
     */
    public function displayValue();

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

    /**
     * Perform clean up before saving coupon to session
     *
     * @return void
     */
    public function cleanBeforeSave();
}

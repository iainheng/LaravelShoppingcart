<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\Exceptions\MemberException;
use Illuminate\Support\Collection;

interface Memberable
{
    public function getId(): int;

    public function getName(): string;

    public function getTier(): string;

    public function getDiscountRate(): float;

    /**
     * Check if member discount is apply to cart or items
     *
     * @return bool
     */
    public function isApplyToCart(): bool;



    public function getDescription(): ?string;

    /**
     * Apply current coupon discount to cart or cart items that will change cart or cart items amount permanently
     *
     * @param Cart $cart
     * @param bool $throwErrors
     * @return float
     * @throws MemberException
     */
    public function apply(Cart $cart, $throwErrors = true);

    /**
     * Forget current coupon discount to cart or cart items that revert changes to cart or cart items amount
     *
     * @param Cart $cart
     * @param bool $throwErrors
     * @return float
     * @throws MemberException
     */
    public function forget(Cart $cart, $throwErrors = true);

    public function isPercentageDiscount(): bool;
}

<?php

namespace Gloudemans\Shoppingcart\Traits;

use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;

/**
 * Class ItemCouponTrait.
 *
 * @property-read CouponDiscountable $discountable
 */
trait ItemCouponTrait
{
    /**
     * The primary key of FQN  of associated discountable
     *
     * @var int
     */
    private $discountableId = null;

    /**
     * The FQN of the associated discountable.
     *
     * @var string|null
     */
    private $discoutableModel = null;

    /**
     * @var bool
     */
    protected $applyOnce;

    /**
     * Sets a discount to an item with what code was used and the discount amount.
     *
     * @param CartItem $item
     */
    public function setDiscountOnItem(CartItem $item)
    {
        $this->applyToCart = false;

        $item->setDiscount($this->value, $this->percentageDiscount, $this->applyOnce);

        $item->setCoupon($this);
    }

    /**
     * Remove discount to an item with what code was used and the discount amount.
     *
     * @param CartItem $item
     */
    public function removeDiscountOnItem(CartItem $item)
    {
        $this->applyToCart = false;

        $item->setDiscount(0, $this->percentageDiscount, $this->applyOnce);

        $item->forgetCoupon();
    }

    /**
     * Associate the cart coupon discountable with the given model.
     *
     * @param mixed $discountable
     *
     * @return CartCoupon
     */
    public function associate($discountable)
    {
        $this->discoutableModel = is_string($discountable) ? $discountable : get_class($discountable);
        $this->discountableId = $discountable->id;

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
                if (isset($this->discoutableModel)) {
                    return with(new $this->discoutableModel)->find($this->discountableId);
                }
            case 'discountableFQCN':
                if (isset($this->associatedModel)) {
                    return $this->associatedModel;
                }
            default:
                return;
        }
    }
}

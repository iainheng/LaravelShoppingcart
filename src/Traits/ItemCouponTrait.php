<?php

namespace Gloudemans\Shoppingcart\Traits;

use Gloudemans\Shoppingcart\CartCoupon;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\CouponDiscountable;
use Gloudemans\Shoppingcart\Coupons\ShippingItemCoupon;

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
    private $discoutableClass = null;

    /**
     * @var bool
     */
    protected $applyOnce;

    /**
     * @var CouponDiscountable
     */
    protected $discountableModel;

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
                return;
        }
    }
}

<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface Buyable
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier($options = null);

    /**
     * Get the title of the Buyable item.
     *
     * @return string
     */
    public function getBuyableName($options = null);

    /**
     * Get the description of the Buyable item.
     *
     * @return string
     */
    public function getBuyableDescription($options = null);

    /**
     * Get the price of the Buyable item.
     *
     * @return float
     */
    public function getBuyablePrice($options = null);

    /**
     * Get the weight of the Buyable item.
     *
     * @return float
     */
    public function getBuyableWeight($options = null);

    /**
     * Get the image url of the Buyable item.
     *
     * @return string
     */
    public function getBuyableImageUrl($options = null);

    /**
     * Check if buyable has stock available for quantity requested
     * @param int $qtyRequired
     * @return bool
     */
    public function isBuyableHasStock($qtyRequired = 1);
}

<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

use Gloudemans\Shoppingcart\Contracts\Buyable;

class BuyableProduct implements Buyable
{
    /**
     * @var int|string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var float
     */
    private $price;

    /**
     * @var float
     */
    private $weight;

    /**
     * @var string
     */
    private $description;

    /**
     * BuyableProduct constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     */
    public function __construct($id = 1, $name = 'Item name', $price = 10.00, $weight = 0, $description = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->weight = $weight;
        $this->description = is_null($description) ? $name : $description;
    }

    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier($options = null)
    {
        return $this->id;
    }

    /**
     * Get the description or title of the Buyable item.
     *
     * @return string
     */
    public function getBuyableDescription($options = null)
    {
        return $this->description;
    }

    /**
     * Get the price of the Buyable item.
     *
     * @return float
     */
    public function getBuyablePrice($options = null)
    {
        return $this->price;
    }

    /**
     * Get the price of the Buyable item.
     *
     * @return float
     */
    public function getBuyableWeight($options = null)
    {
        return $this->weight;
    }

    /**
     * @inheritDoc
     */
    public function getBuyableName($options = null)
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getBuyableImageUrl($options = null)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function isBuyableHasStock($qtyRequired = 1)
    {
        return true;
    }
}

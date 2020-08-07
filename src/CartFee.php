<?php

namespace Gloudemans\Shoppingcart;

use Gloudemans\Shoppingcart\Contracts\Buyable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class CartFee extends CartItem implements Arrayable, Jsonable
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $description;

    /**
     * CartFee constructor.
     * @param $id
     * @param $type
     * @param $name
     * @param $price
     * @param string $description
     * @param int $weight
     * @param array $options
     */
    public function __construct($id, $type, $name, $price, $description = '', $weight = 0, array $options = [])
    {
        parent::__construct($id, $name, $price, $weight, $options);

        if (empty($type)) {
            throw new \InvalidArgumentException('Please supply a valid type.');
        }

        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }
        if (!is_numeric($price)) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }
        if (!is_numeric($weight)) {
            throw new \InvalidArgumentException('Please supply a valid weight.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->price = floatval($price);
        $this->weight = floatval($weight);
        $this->options = new CartItemOptions($options);
        $this->rowId = $this->generateRowId($id, $options);
        $this->type = $type;
        $this->description = $description;
        $this->qty = 1;
    }

    public static function fromBuyable(Buyable $item, array $options = [])
    {
        throw new \InvalidArgumentException('Cart fee cannot create from buyable', 500);
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

        return new self($attributes['id'], $attributes['type'], $attributes['name'], $attributes['price'],
            Arr::get($attributes, 'description'), Arr::get($attributes, 'weight', 0), $options);
    }

    /**
     * @param int|string $id
     * @param string $name
     * @param float $price
     * @param $weight
     * @param array $options
     * @return CartItem|void
     */
    public static function fromAttributes($id, $name, $price, $weight, array $options = [])
    {
        throw new InvalidParameterException('Use fromParameters() with additional attributes');
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     *
     * @return \Gloudemans\Shoppingcart\CartFee
     */
    public static function fromParameters($id, $type, $name, $price, $description = null, $weight = 0, array $options = [])
    {
        return new self($id, $type, $name, $price, $description, $weight, $options);
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
        $this->type = Arr::get($attributes, 'type', $this->type);
        $this->qty = Arr::get($attributes, 'qty', $this->qty);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->description = Arr::get($attributes, 'description', $this->description);
        $this->weight = Arr::get($attributes, 'weight', $this->weight);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }
}

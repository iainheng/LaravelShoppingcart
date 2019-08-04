<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'tax' => 21,

    /*
    |--------------------------------------------------------------------------
    | Shoppingcart database settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the connection that the shoppingcart should use when
    | storing and restoring a cart.
    |
    */

    'database' => [

        'connection' => null,

        'table' => 'shoppingcart',

    ],

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formatted numbers if you don't
    | set them in the method call.
    |
    */

    'format' => [

        'decimals' => 2,

        'decimal_point' => '.',

        'thousand_separator' => ',',

    ],

    /*
    |--------------------------------------------------------------------------
    | Default date format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formatted date if you don't
    | set them in the method call.
    |
    */

    'date_format' => 'j M Y',

    /*
    |--------------------------------------------------------------------------
    | Default session keys
    |--------------------------------------------------------------------------
    |
    | This default array key names used in session
    |
    */

    'session' => [
        'shipping' => 'shipping',
        'billing' => 'billing',
        'payment' => 'payment',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default shipping fee type name
    |--------------------------------------------------------------------------
    |
    | This default shipping fee name will be use as fee id
    |
    */

    'shipping_type_id' => 'shipping',

    /*
    |--------------------------------------------------------------------------
    | Allow multiple coupon of same type
    |--------------------------------------------------------------------------
    |
    | Control if multiple coupon of same type can be applied to a cart.
    |
    | e.g. multiple coupon that discount on items amount
    |
    */

    'allow_multiple_same_type_discount' => false,

    /*
    |--------------------------------------------------------------------------
    | Discount and coupon
    |--------------------------------------------------------------------------
    |
    | Configure coupon behavior and discount application
    |
    */

    'discount' => [
        'coupon_label' => 'discount code', // 'coupon'

        'discount_on_fees' => false,

        'tax_item_before_discount' => false
    ],
];

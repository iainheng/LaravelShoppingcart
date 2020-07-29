<?php

namespace Gloudemans\Shoppingcart\Exceptions;

use Gloudemans\Shoppingcart\Contracts\Couponable;
use Throwable;

/**
 * Class CouponException.
 */
class CouponException extends \Exception
{
    protected $couponable;

    public function __construct($message = "", $code = 0, Throwable $previous = null, Couponable $couponable = null)
    {
        parent::__construct($message, $code, $previous);

        $this->couponable = $couponable;
    }

    /**
     * @return Couponable|null
     */
    public function getCouponable()
    {
        return $this->couponable;
    }

}

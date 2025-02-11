<?php

namespace Gloudemans\Shoppingcart\Exceptions;

use Gloudemans\Shoppingcart\Contracts\Couponable;
use Gloudemans\Shoppingcart\Contracts\Voucherable;
use Throwable;

class VoucherException extends \Exception
{
    protected $voucherable;

    public function __construct($message = "", $code = 0, Throwable $previous = null, Voucherable $voucherable = null)
    {
        parent::__construct($message, $code, $previous);

        $this->voucherable = $voucherable;
    }

    /**
     * @return Voucherable|null
     */
    public function getVoucherable()
    {
        return $this->voucherable;
    }

}

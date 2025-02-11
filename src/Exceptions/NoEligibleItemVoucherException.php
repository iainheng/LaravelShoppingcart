<?php

namespace Gloudemans\Shoppingcart\Exceptions;

use Gloudemans\Shoppingcart\Contracts\Couponable;
use Gloudemans\Shoppingcart\Contracts\Voucherable;
use Throwable;

class NoEligibleItemVoucherException extends VoucherException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null, Voucherable $voucherable = null)
    {
        parent::__construct($message, $code, $previous, $voucherable);

        if (empty($message)) {
            $this->message = "No eligible items found for voucher discount";
        }
    }
}

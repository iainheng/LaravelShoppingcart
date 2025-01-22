<?php

namespace Gloudemans\Shoppingcart\Exceptions;

use Gloudemans\Shoppingcart\Contracts\Memberable;
use Throwable;

class MemberException extends \Exception
{
    protected ?Memberable $memberable;

    public function __construct($message = "", $code = 0, Throwable $previous = null, Memberable $memberable = null)
    {
        parent::__construct($message, $code, $previous);

        $this->memberable = $memberable;
    }

    /**
     * @return Memberable|null
     */
    public function getMemberable()
    {
        return $this->memberable;
    }

}

<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Illuminate\Support\Collection;

interface MemberDiscountable
{
    public function getMemberDiscountableIdentifiers($options = null): Collection;

    public function getMemberDiscountableDescription($options = null): string;

    public function getMemberDiscountableProducts(): Collection;
}

<?php
/**
 * Calculate actual tax rate based on country tax rate (in %)
 *
 * @param double $rate in percentage (%)
 * @return double
 */
if (! function_exists('tax_rate_amount')) {
    function tax_rate_amount($rate)
    {
        return $rate / 100;
    }
}

/**
 * Get total taxed amount based on price
 *
 * @param double $price
 * @param double $rate in %
 * @param boolean $tax_included
 * @param string $date
 *
 * return decimal
 */
if (! function_exists('calc_tax_amount')) {
    function calc_tax_amount($price, $rateInPercent, $taxIncluded = false, $date = null)
    {
        $rateAmount = tax_rate_amount($rateInPercent);

        $amount = (!$taxIncluded) ? $price * $rateAmount : (($rateAmount * $price) / (1 + $rateAmount));

        return round($amount, 2);
    }
}
if (! function_exists('price_exclude_tax')) {
    function price_exclude_tax($total, $taxRate = 0.06, $isInclusive = true)
    {
        return ($isInclusive) ? round($total / (1 + $taxRate), 2) : $total;
    }
}
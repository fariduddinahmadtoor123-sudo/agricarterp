<?php

namespace App\Services\PurchasingInventory;

class PriceCodeWordingEncoder
{
    /**
     * @param  array<int|string, string>  $wording
     */
    public function encodeSalePrice(string $saleRate, array $wording): string
    {
        $normalized = str_replace(',', '', trim($saleRate));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return '';
        }

        $number = (float) $normalized;

        if ($number <= 0) {
            return '';
        }

        $digits = rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');

        if ($digits === '') {
            return '';
        }

        $encoded = '';

        foreach (str_split($digits) as $character) {
            if ($character === '.') {
                continue;
            }

            $encoded .= $wording[$character] ?? $wording[(int) $character] ?? $character;
        }

        return $encoded;
    }
}

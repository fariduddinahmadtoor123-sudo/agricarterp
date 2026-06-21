<?php

namespace App\Services\Contacts;

class MobileNumberNormalizer
{
    public function normalize(?string $mobile): ?string
    {
        if (blank($mobile)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '92' . substr($digits, 1);
        }

        return $digits;
    }
}

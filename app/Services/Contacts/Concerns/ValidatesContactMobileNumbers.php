<?php

namespace App\Services\Contacts\Concerns;

use App\Services\Contacts\MobileNumberNormalizer;
use Illuminate\Validation\ValidationException;

trait ValidatesContactMobileNumbers
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertPrimaryMobileIsNormalizable(array $data): void
    {
        $normalizer = app(MobileNumberNormalizer::class);

        if ($normalizer->normalize($data['mobile_number'] ?? null) === null) {
            throw ValidationException::withMessages([
                'mobile_number' => 'Enter a valid mobile number.',
            ]);
        }
    }
}

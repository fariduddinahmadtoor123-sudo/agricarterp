<?php

namespace App\Services\Contacts;

use App\Models\CustomerNumberSequence;
use Illuminate\Support\Facades\DB;

class CustomerCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = CustomerNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'CUS-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

<?php

namespace App\Services\Contacts;

use App\Models\SupplierNumberSequence;
use Illuminate\Support\Facades\DB;

class SupplierCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = SupplierNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'SUP-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

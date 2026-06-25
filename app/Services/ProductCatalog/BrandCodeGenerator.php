<?php

namespace App\Services\ProductCatalog;

use App\Models\BrandNumberSequence;
use Illuminate\Support\Facades\DB;

class BrandCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = BrandNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'BRN-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

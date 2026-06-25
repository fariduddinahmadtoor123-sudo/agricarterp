<?php

namespace App\Services\ProductCatalog;

use App\Models\CategoryNumberSequence;
use Illuminate\Support\Facades\DB;

class CategoryCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = CategoryNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'CAT-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

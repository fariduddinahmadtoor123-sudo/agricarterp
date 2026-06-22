<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductNumberSequence;
use Illuminate\Support\Facades\DB;

class ProductCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = ProductNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'PRD-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

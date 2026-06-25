<?php

namespace App\Services\ProductCatalog;

use App\Models\ControlGroupNumberSequence;
use Illuminate\Support\Facades\DB;

class ProductControlGroupCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = ControlGroupNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'CTG-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

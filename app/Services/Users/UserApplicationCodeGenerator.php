<?php

namespace App\Services\Users;

use App\Models\UserApplicationNumberSequence;
use Illuminate\Support\Facades\DB;

class UserApplicationCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = UserApplicationNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'APP-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

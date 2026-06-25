<?php

namespace App\Services\Users;

use App\Models\UserNumberSequence;
use Illuminate\Support\Facades\DB;

class UserCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = UserNumberSequence::query()
                ->lockForUpdate()
                ->findOrFail(1);

            $sequence->last_number = ($sequence->last_number ?? 0) + 1;
            $sequence->save();

            return 'USR-' . str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}

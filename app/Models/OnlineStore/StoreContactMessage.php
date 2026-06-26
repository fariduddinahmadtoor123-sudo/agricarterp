<?php

namespace App\Models\OnlineStore;

use Illuminate\Database\Eloquent\Model;

class StoreContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'message',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}

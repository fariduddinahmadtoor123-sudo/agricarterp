<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNumberSequence extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}

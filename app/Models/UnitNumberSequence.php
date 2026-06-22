<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitNumberSequence extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $fillable = [
        'last_number',
    ];
}

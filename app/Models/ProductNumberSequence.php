<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductNumberSequence extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'last_number',
    ];
}

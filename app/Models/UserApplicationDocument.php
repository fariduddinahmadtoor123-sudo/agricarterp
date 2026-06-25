<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApplicationDocument extends Model
{
    protected $fillable = [
        'user_application_id',
        'profile_photo_path',
        'card_front_path',
        'card_back_path',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(UserApplication::class, 'user_application_id');
    }
}

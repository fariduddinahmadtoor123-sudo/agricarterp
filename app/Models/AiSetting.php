<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $fillable = [
        'openrouter_api_key',
        'vision_model',
        'enrichment_enabled',
        'batch_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrichment_enabled' => 'boolean',
            'batch_limit' => 'integer',
        ];
    }
}

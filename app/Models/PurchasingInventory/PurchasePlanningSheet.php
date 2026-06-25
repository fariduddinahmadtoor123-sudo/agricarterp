<?php

namespace App\Models\PurchasingInventory;

use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePlanningSheet extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'sheet_number',
        'status',
        'title',
        'sheet_date',
        'name_lang',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sheet_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchasePlanningSheetLine::class, 'sheet_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

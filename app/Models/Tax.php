<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'code',
        'type',
        'rate_value',
        'apply_on',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_value' => 'decimal:4',
            'apply_on' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPercentage(): bool
    {
        return $this->type === self::TYPE_PERCENTAGE;
    }

    public function formattedRate(): string
    {
        $value = rtrim(rtrim(number_format((float) $this->rate_value, 4, '.', ''), '0'), '.');

        return $this->isPercentage() ? $value . '%' : $value;
    }

    /**
     * @return list<string>
     */
    public function applyOnLabels(): array
    {
        $options = config('tax.apply_on', []);

        return collect($this->apply_on ?? [])
            ->map(fn (mixed $key): string => (string) ($options[(string) $key] ?? ucfirst((string) $key)))
            ->values()
            ->all();
    }
}

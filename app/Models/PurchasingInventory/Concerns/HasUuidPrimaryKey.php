<?php

namespace App\Models\PurchasingInventory\Concerns;

use Illuminate\Support\Str;

trait HasUuidPrimaryKey
{
    public function initializeHasUuidPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasUuidPrimaryKey(): void
    {
        static::creating(function (self $model): void {
            if ($model->getKey() === null) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}

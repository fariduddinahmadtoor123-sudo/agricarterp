<?php

namespace App\Services\ProductCatalog;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CategoryImageStorage
{
    public function disk(): string
    {
        return (string) config('product-catalog.category_image_disk', 'local');
    }

    public function extractPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = Arr::first($value);
        }

        return filled($value) ? (string) $value : null;
    }

    public function cleanupIfReplaced(?string $oldPath, ?string $newPath): void
    {
        if (filled($oldPath) && $oldPath !== $newPath) {
            $this->deleteIfExists($oldPath);
        }
    }

    public function deleteIfExists(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $disk = Storage::disk($this->disk());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}

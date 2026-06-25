<?php

namespace App\Services\Ai;

use App\Services\ProductCatalog\CategoryImageStorage;
use App\Services\ProductCatalog\ProductImageStorage;
use Illuminate\Support\Facades\Storage;

class CatalogImageEncoder
{
    public function __construct(
        protected CategoryImageStorage $categoryImageStorage,
        protected ProductImageStorage $productImageStorage,
    ) {}

    public function encodeCategoryImage(?string $imagePath): ?string
    {
        return $this->encodeFromStorage($this->categoryImageStorage->locate($imagePath));
    }

    public function encodeProductImage(?string $imagePath): ?string
    {
        return $this->encodeFromStorage($this->productImageStorage->locate($imagePath));
    }

    /**
     * @param  array{disk: string, path: string}|null  $located
     */
    protected function encodeFromStorage(?array $located): ?string
    {
        if ($located === null) {
            return null;
        }

        $disk = Storage::disk($located['disk']);
        $path = $located['path'];

        if (! $disk->exists($path)) {
            return null;
        }

        $mimeType = $disk->mimeType($path) ?: 'image/jpeg';
        $contents = $disk->get($path);

        if ($contents === null || $contents === '') {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }
}

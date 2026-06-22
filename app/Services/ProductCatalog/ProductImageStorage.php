<?php

namespace App\Services\ProductCatalog;

use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageStorage
{
    public const SERVING_ROUTE = 'product-catalog.product-images';

    public const SERVING_PANEL = 'admin';

    public function disk(): string
    {
        return (string) config('product-catalog.product_image_disk', 'local');
    }

    public function extractPath(mixed $value): ?string
    {
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = Arr::first($decoded);
            }
        }

        if (is_array($value)) {
            $value = Arr::first($value);
        }

        return filled($value) ? (string) $value : null;
    }

    public function normalizePath(mixed $rawPath): ?string
    {
        $path = $this->extractPath($rawPath);

        if (blank($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if (str_contains($path, '://')) {
            $path = parse_url($path, PHP_URL_PATH) ?? $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    public function locate(mixed $rawPath): ?array
    {
        $path = $this->normalizePath($rawPath);

        if (blank($path)) {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $path,
            str_starts_with($path, 'products/') ? null : 'products/' . $path,
        ])));

        foreach (array_unique([$this->disk(), 'local', 'public']) as $diskName) {
            $disk = Storage::disk($diskName);

            foreach ($candidates as $candidate) {
                if ($disk->exists($candidate)) {
                    return [
                        'disk' => $diskName,
                        'path' => $candidate,
                    ];
                }
            }
        }

        return null;
    }

    public function catalogUrl(mixed $rawPath): ?string
    {
        $located = $this->locate($rawPath);

        if ($located === null) {
            return null;
        }

        return route('catalog.product-images', ['path' => $located['path']]);
    }

    public function cleanupIfReplaced(?string $oldPath, ?string $newPath): void
    {
        if (filled($oldPath) && $oldPath !== $newPath) {
            $this->deleteIfExists($oldPath);
        }
    }

    public function url(?string $path): ?string
    {
        $located = $this->locate($path);

        if ($located === null) {
            return null;
        }

        if ($located['disk'] === 'public') {
            return Storage::disk('public')->url($located['path']);
        }

        return $this->servingUrl($located['path']);
    }

    public function servingUrl(string $path): string
    {
        try {
            return Filament::getPanel(static::SERVING_PANEL)->route(
                static::SERVING_ROUTE,
                ['path' => $path],
            );
        } catch (\Throwable) {
            return url('/admin/product-images?' . http_build_query(['path' => $path]));
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

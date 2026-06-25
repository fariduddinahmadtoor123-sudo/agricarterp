<?php

namespace App\Services\Settings;

use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanySettingLogoStorage
{
    public const SERVING_ROUTE = 'settings.company-setting-logo';

    public const SERVING_PANEL = 'admin';

    public function disk(): string
    {
        return (string) config('settings.logo_disk', 'local');
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
            str_starts_with($path, 'company-settings/') ? null : 'company-settings/' . $path,
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
            return url('/admin/company-setting-logo?' . http_build_query(['path' => $path]));
        }
    }

    public function deleteIfExists(?string $path): void
    {
        $located = $this->locate($path);

        if ($located === null) {
            return;
        }

        Storage::disk($located['disk'])->delete($located['path']);
    }
}

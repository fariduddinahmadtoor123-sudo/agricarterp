<?php

namespace App\Services\OnlineStore;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class StoreFooterLogoStorage
{
    public function disk(): string
    {
        return (string) config('online-store.footer_logo_disk', 'local');
    }

    public function directory(): string
    {
        return (string) config('online-store.footer_logo_directory', 'online-store/footer');
    }

    public function persistFromFormValue(mixed $value, ?string $existingPath = null): ?string
    {
        $ingested = $this->ingestFromForm($value);

        if (filled($ingested)) {
            return $ingested;
        }

        if (filled($existingPath) && $this->locate($existingPath) !== null) {
            return $this->normalizePath($existingPath);
        }

        return null;
    }

    /**
     * Store a new upload from the admin form.
     */
    public function ingestFromForm(mixed $value): ?string
    {
        foreach ($this->flattenUploadValues($value) as $item) {
            if ($item instanceof TemporaryUploadedFile) {
                $stored = $this->storeTemporaryUpload($item);

                if (filled($stored)) {
                    return $stored;
                }
            }
        }

        foreach ($this->flattenUploadValues($value) as $item) {
            if (! is_string($item) || blank($item)) {
                continue;
            }

            if ($this->isTemporaryPath($item) && is_readable($item)) {
                $extension = pathinfo($item, PATHINFO_EXTENSION) ?: 'webp';
                $filename = Str::uuid() . '.' . $extension;
                $target = trim($this->directory(), '/') . '/' . $filename;

                Storage::disk($this->disk())->put($target, (string) file_get_contents($item));

                return $this->normalizePath($target);
            }

            $located = $this->locate($item);

            if ($located !== null) {
                return $located['path'];
            }
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    protected function flattenUploadValues(mixed $value): array
    {
        if ($value instanceof TemporaryUploadedFile) {
            return [$value];
        }

        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $this->flattenUploadValues($decoded);
            }
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && filled($value)) {
            return [$value];
        }

        return [];
    }

    public function extractPath(mixed $value): ?string
    {
        return $this->ingestFromForm($value);
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
        return $this->rootedRoute('store.footer-logo', ['path' => $path]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function rootedRoute(string $name, array $parameters = []): string
    {
        $relative = route($name, $parameters, false);

        if (app()->bound('request')) {
            $request = request();
            $baseUrl = $request->getBaseUrl();

            if ($baseUrl !== '') {
                return rtrim($request->getSchemeAndHttpHost() . $baseUrl, '/') . $relative;
            }

            $configuredUrl = rtrim((string) config('app.url'), '/');
            $configuredHost = parse_url($configuredUrl, PHP_URL_HOST) ?: '';
            $configuredPath = parse_url($configuredUrl, PHP_URL_PATH) ?: '';

            if (
                $configuredHost === $request->getHost()
                && $configuredPath !== ''
                && $configuredPath !== '/'
            ) {
                return $configuredUrl . $relative;
            }
        }

        return route($name, $parameters);
    }

    public function cleanupIfReplaced(?string $oldPath, ?string $newPath): void
    {
        $oldPath = $this->normalizePath($oldPath);
        $newPath = $this->normalizePath($newPath);

        if (blank($oldPath) || $oldPath === $newPath) {
            return;
        }

        $this->deleteIfExists($oldPath);
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    public function locate(mixed $rawPath): ?array
    {
        $path = $this->normalizePath($rawPath);

        if (blank($path) || $this->isTemporaryPath($path)) {
            return null;
        }

        $directory = trim($this->directory(), '/');

        $candidates = array_values(array_unique(array_filter([
            $path,
            str_starts_with($path, $directory . '/') ? null : $directory . '/' . $path,
            str_starts_with($path, $directory . '/') ? Str::after($path, $directory . '/') : null,
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

    public function deleteIfExists(?string $path): void
    {
        $located = $this->locate($path);

        if ($located === null) {
            return;
        }

        Storage::disk($located['disk'])->delete($located['path']);
    }

    protected function storeTemporaryUpload(TemporaryUploadedFile $file): ?string
    {
        $stored = $file->store($this->directory(), $this->disk());

        return filled($stored) ? $this->normalizePath($stored) : null;
    }

    protected function normalizePath(?string $path): ?string
    {
        if (blank($path) || $this->isTemporaryPath($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if (str_contains($path, '://')) {
            $path = parse_url($path, PHP_URL_PATH) ?? $path;
        }

        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    protected function isTemporaryPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', strtolower($path));

        return str_contains($normalized, '/temp/')
            || str_contains($normalized, '/tmp/')
            || str_contains($normalized, 'appdata/local/temp')
            || str_ends_with($normalized, '.tmp');
    }
}

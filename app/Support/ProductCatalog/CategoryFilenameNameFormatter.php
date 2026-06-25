<?php

namespace App\Support\ProductCatalog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CategoryFilenameNameFormatter
{
    public static function fromUploadState(mixed $state): ?string
    {
        $filename = static::extractOriginalFilename($state);

        if ($filename === null) {
            return null;
        }

        return static::format($filename);
    }

    /**
     * Only derive a name from a fresh client upload — not from stored disk paths.
     */
    public static function fromNewUpload(mixed $state): ?string
    {
        if (! $state instanceof TemporaryUploadedFile && ! $state instanceof UploadedFile) {
            return null;
        }

        return static::fromUploadState($state);
    }

    public static function format(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $normalized = Str::of($base)
            ->replaceMatches('/[-_]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        if ($normalized === '') {
            return '';
        }

        return collect(explode(' ', $normalized))
            ->map(fn (string $word): string => $word === '&' ? '&' : Str::title($word))
            ->implode(' ');
    }

    public static function extractOriginalFilename(mixed $state): ?string
    {
        if ($state instanceof TemporaryUploadedFile || $state instanceof UploadedFile) {
            return $state->getClientOriginalName();
        }

        if (is_array($state)) {
            foreach ($state as $item) {
                $filename = static::extractOriginalFilename($item);

                if ($filename !== null) {
                    return $filename;
                }
            }

            return null;
        }

        if (is_string($state) && $state !== '') {
            return basename($state);
        }

        return null;
    }
}

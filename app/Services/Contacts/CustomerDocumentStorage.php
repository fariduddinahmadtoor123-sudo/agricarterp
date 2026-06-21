<?php

namespace App\Services\Contacts;

use App\Models\CustomerDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CustomerDocumentStorage
{
    public const FIELD_PROFILE_PHOTO = 'profile_photo_path';

    /**
     * @var array<string, string>
     */
    public const FORM_TO_COLUMN = [
        'profile_photo' => self::FIELD_PROFILE_PHOTO,
    ];

    public function disk(): string
    {
        return (string) config('contacts.customer_documents_disk', 'local');
    }

    /**
     * @param  array<string, mixed>  $documents
     * @return array<string, string|null>
     */
    public function resolvePaths(array $documents): array
    {
        $paths = [];

        foreach (self::FORM_TO_COLUMN as $formKey => $column) {
            $paths[$column] = $this->extractFilePath($documents[$formKey] ?? null);
        }

        return $paths;
    }

    /**
     * @param  array<string, string|null>  $newPaths
     */
    public function cleanupReplacedFiles(CustomerDocument $document, array $newPaths): void
    {
        foreach (self::FORM_TO_COLUMN as $column) {
            $oldPath = $document->{$column};
            $newPath = $newPaths[$column] ?? null;

            if (filled($oldPath) && $oldPath !== $newPath) {
                $this->deleteIfExists($oldPath);
            }
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

    protected function extractFilePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = Arr::first($value);
        }

        return filled($value) ? (string) $value : null;
    }
}

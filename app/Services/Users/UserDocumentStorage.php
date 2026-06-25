<?php

namespace App\Services\Users;

use App\Models\UserDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UserDocumentStorage
{
    public const FIELD_PROFILE_PHOTO = 'profile_photo_path';

    public const FIELD_CARD_FRONT = 'card_front_path';

    public const FIELD_CARD_BACK = 'card_back_path';

    /**
     * @var array<string, string>
     */
    public const FORM_TO_COLUMN = [
        'profile_photo' => self::FIELD_PROFILE_PHOTO,
        'card_front' => self::FIELD_CARD_FRONT,
        'card_back' => self::FIELD_CARD_BACK,
    ];

    public function disk(): string
    {
        return (string) config('users.document_disk', 'local');
    }

    public function directory(): string
    {
        return (string) config('users.documents_directory', 'users/documents');
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
    public function cleanupReplacedFiles(UserDocument $document, array $newPaths): void
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

<?php

namespace App\Services\Users;

use App\Models\UserApplicationDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UserApplicationDocumentStorage
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
        return (string) config('users.application_documents_directory', 'users/applications/documents');
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

    /**
     * @param  array<string, string|null>  $paths
     */
    public function copyToUserStorage(array $paths): array
    {
        $userStorage = app(UserDocumentStorage::class);
        $copied = [];

        foreach (self::FORM_TO_COLUMN as $column) {
            $sourcePath = $paths[$column] ?? null;

            if (blank($sourcePath)) {
                $copied[$column] = null;

                continue;
            }

            $disk = Storage::disk($this->disk());
            $filename = basename($sourcePath);
            $destination = rtrim($userStorage->directory(), '/') . '/' . uniqid('usr_', true) . '_' . $filename;

            if ($disk->exists($sourcePath)) {
                $disk->copy($sourcePath, $destination);
                $copied[$column] = $destination;
            } else {
                $copied[$column] = null;
            }
        }

        return $copied;
    }

    protected function extractFilePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = Arr::first($value);
        }

        return filled($value) ? (string) $value : null;
    }
}

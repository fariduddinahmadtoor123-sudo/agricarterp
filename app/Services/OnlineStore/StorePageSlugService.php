<?php

namespace App\Services\OnlineStore;

use App\Models\OnlineStore\StorePage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorePageSlugService
{
    public function generate(string $titleEn, ?int $ignoreId = null): string
    {
        $base = Str::slug($titleEn);

        if ($base === '') {
            $base = 'page';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return StorePage::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeSlug(array $data, ?StorePage $page = null): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($slug === '' && filled($data['title_en'] ?? null)) {
            $slug = $this->generate((string) $data['title_en'], $page?->id);
        }

        $data['slug'] = Str::slug($slug);

        if ($data['slug'] === '') {
            throw ValidationException::withMessages(['slug' => 'A valid slug is required.']);
        }

        if ($this->slugExists($data['slug'], $page?->id)) {
            throw ValidationException::withMessages(['slug' => 'This slug is already in use.']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?StorePage $page = null): void
    {
        validator($data, [
            'title_en' => ['required', 'string', 'max:255'],
            'title_ur' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('store_pages', 'slug')->ignore($page?->id),
            ],
            'content_en' => ['nullable'],
            'content_ur' => ['nullable'],
            'is_published' => ['boolean'],
        ])->validate();
    }
}

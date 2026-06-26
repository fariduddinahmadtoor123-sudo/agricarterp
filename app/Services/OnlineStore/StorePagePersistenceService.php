<?php

namespace App\Services\OnlineStore;

use App\Models\OnlineStore\StorePage;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\DB;

class StorePagePersistenceService
{
    public function __construct(
        protected StorePageSlugService $slugs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StorePage
    {
        $data = $this->prepare($data);
        $this->slugs->validate($data);

        return DB::transaction(fn (): StorePage => StorePage::query()->create([
            ...$this->attributes($data),
            'created_by' => auth()->id(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StorePage $page, array $data): StorePage
    {
        $data = $this->prepare($data, $page);
        $this->slugs->validate($data, $page);

        return DB::transaction(function () use ($page, $data): StorePage {
            $page->update($this->attributes($data));

            return $page->fresh();
        });
    }

    public function delete(StorePage $page): void
    {
        $page->delete();
    }

    public function renderContent(?string $stored): string
    {
        if (blank($stored)) {
            return '';
        }

        $trimmed = trim($stored);

        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return RichContentRenderer::make($stored)->toHtml();
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?StorePage $page = null): array
    {
        $data['title_en'] = trim((string) ($data['title_en'] ?? ''));
        $data['title_ur'] = trim((string) ($data['title_ur'] ?? ''));
        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        return $this->slugs->normalizeSlug($data, $page);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'title_en' => $data['title_en'],
            'title_ur' => $data['title_ur'],
            'slug' => $data['slug'],
            'content_en' => $this->normalizeRichContent($data['content_en'] ?? null),
            'content_ur' => $this->normalizeRichContent($data['content_ur'] ?? null),
            'is_published' => $data['is_published'],
        ];
    }

    protected function normalizeRichContent(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }
}

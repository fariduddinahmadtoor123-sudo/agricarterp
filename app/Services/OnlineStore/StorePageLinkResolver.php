<?php

namespace App\Services\OnlineStore;

use App\Models\OnlineStore\StorePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StorePageLinkResolver
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function resolveLinks(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $pageIds = collect($rows)
            ->pluck('store_page_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        /** @var Collection<int, StorePage> $pages */
        $pages = StorePage::query()
            ->whereIn('id', $pageIds)
            ->where('is_published', true)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($rows as $row) {
            $pageId = (int) ($row['store_page_id'] ?? 0);
            $page = $pages->get($pageId);

            if ($page === null) {
                continue;
            }

            $label = $page->title_en;

            $resolved[] = [
                'label' => $label,
                'url' => route('store.page', ['slug' => $page->slug]),
            ];
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public function draftTitlesInRows(array $rows): array
    {
        $pageIds = collect($rows)
            ->pluck('store_page_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($pageIds->isEmpty()) {
            return [];
        }

        return StorePage::query()
            ->whereIn('id', $pageIds)
            ->where('is_published', false)
            ->orderBy('title_en')
            ->pluck('title_en')
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  ...$linkGroups
     * @return list<string>
     */
    public function draftTitlesInLinkGroups(array ...$linkGroups): array
    {
        return collect($linkGroups)
            ->flatMap(fn (array $rows): array => $this->draftTitlesInRows($rows))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function pageOptionsForPicker(): array
    {
        return $this->pageOptionQuery()
            ->get()
            ->mapWithKeys(fn (StorePage $page): array => [
                (string) $page->id => $this->formatPageOptionLabel($page),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function searchPagesForPicker(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return $this->pageOptionsForPicker();
        }

        $like = '%' . addcslashes($search, '%_\\') . '%';

        return $this->pageOptionQuery()
            ->where(function ($query) use ($like): void {
                $query
                    ->where('title_en', 'like', $like)
                    ->orWhere('title_ur', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (StorePage $page): array => [
                (string) $page->id => $this->formatPageOptionLabel($page),
            ])
            ->all();
    }

    public function pagePickerLabel(mixed $pageId): ?string
    {
        if (blank($pageId)) {
            return null;
        }

        $page = StorePage::query()->find((int) $pageId);

        return $page ? $this->formatPageOptionLabel($page) : null;
    }

    /**
     * @return array<int, string>
     */
    public function publishedPageOptions(): array
    {
        return StorePage::query()
            ->where('is_published', true)
            ->orderBy('title_en')
            ->pluck('title_en', 'id')
            ->all();
    }

    protected function pageOptionQuery(): Builder
    {
        return StorePage::query()
            ->orderByDesc('is_published')
            ->orderBy('title_en');
    }

    protected function formatPageOptionLabel(StorePage $page): string
    {
        if ($page->is_published) {
            return $page->title_en;
        }

        return $page->title_en . ' (Draft)';
    }
}

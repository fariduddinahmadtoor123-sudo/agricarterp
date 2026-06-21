<?php

namespace App\Support\Navigation;

class NavigationSearcher
{
    /**
     * @param  array<int, array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}>  $entries
     * @return array<int, array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}>
     */
    public function search(string $query, array $entries, int $limit = 12): array
    {
        $query = trim(mb_strtolower($query));

        if ($query === '') {
            return [];
        }

        $terms = preg_split('/\s+/', $query) ?: [];
        $scored = [];

        foreach ($entries as $entry) {
            $score = $this->scoreEntry($entry, $terms, $query);

            if ($score > 0) {
                $scored[] = [
                    'entry' => $entry,
                    'score' => $score,
                ];
            }
        }

        usort($scored, function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            return strcmp($left['entry']['breadcrumb'], $right['entry']['breadcrumb']);
        });

        return array_map(
            fn (array $row): array => $row['entry'],
            array_slice($scored, 0, $limit),
        );
    }

    /**
     * @param  array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}  $entry
     * @param  array<int, string>  $terms
     */
    protected function scoreEntry(array $entry, array $terms, string $query): int
    {
        $label = mb_strtolower($entry['label']);
        $breadcrumb = mb_strtolower($entry['breadcrumb']);
        $module = mb_strtolower($entry['module']);
        $keywords = $entry['keywords'];

        $score = 0;

        if ($label === $query) {
            $score += 120;
        } elseif (str_starts_with($label, $query)) {
            $score += 95;
        } elseif (str_contains($label, $query)) {
            $score += 75;
        }

        if (str_contains($breadcrumb, $query)) {
            $score += 45;
        }

        if (str_starts_with($module, $query)) {
            $score += 40;
        }

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            if ($label === $term) {
                $score += 80;
            } elseif (str_starts_with($label, $term)) {
                $score += 60;
            } elseif (str_contains($label, $term)) {
                $score += 50;
            }

            if (str_contains($keywords, $term)) {
                $score += 25;
            }

            if (str_contains($module, $term)) {
                $score += 15;
            }
        }

        return $score;
    }
}

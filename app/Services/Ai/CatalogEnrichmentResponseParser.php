<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiEnrichmentException;

class CatalogEnrichmentResponseParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $content, $matches) === 1) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiEnrichmentException('AI response was not valid JSON.');
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public function normalize(array $decoded): array
    {
        $aliases = [
            'name_ur' => ['urdu_name', 'urduName', 'nameUr', 'title_ur', 'product_name_ur'],
            'description_ur' => ['urdu_description', 'descriptionUr'],
            'short_description_ur' => ['short_descriptionUr', 'urdu_short_description'],
        ];

        foreach ($aliases as $canonical => $alternatives) {
            if ($this->hasUsableValue($decoded[$canonical] ?? null)) {
                continue;
            }

            foreach ($alternatives as $alternative) {
                if ($this->hasUsableValue($decoded[$alternative] ?? null)) {
                    $decoded[$canonical] = $decoded[$alternative];
                    break;
                }
            }
        }

        return $decoded;
    }

    protected function hasUsableValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }
}

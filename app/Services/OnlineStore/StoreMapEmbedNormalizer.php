<?php

namespace App\Services\OnlineStore;

class StoreMapEmbedNormalizer
{
    public function normalize(?string $input): ?string
    {
        $input = trim(html_entity_decode((string) $input, ENT_QUOTES | ENT_HTML5));

        if ($input === '') {
            return null;
        }

        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $input, $matches) === 1) {
            return $this->cleanUrl($matches[1]);
        }

        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return $this->cleanUrl($input);
        }

        if (preg_match('/https?:\/\/[^\s"\'<>]+/i', $input, $matches) === 1) {
            return $this->cleanUrl($matches[0]);
        }

        return null;
    }

    protected function cleanUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

        if ($url === '' || ! str_contains($url, 'google.com/maps')) {
            return null;
        }

        return $url;
    }
}

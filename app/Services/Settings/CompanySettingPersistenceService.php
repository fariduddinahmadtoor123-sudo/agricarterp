<?php

namespace App\Services\Settings;

use App\Models\CompanySetting;
use App\Services\Contacts\MobileNumberNormalizer;
use App\Support\Authorization\PermissionChecker;
use App\Support\Settings\CompanySettingAuthorization;
use Illuminate\Support\Facades\DB;

class CompanySettingPersistenceService
{
    public function __construct(
        protected CompanySettingDataValidator $dataValidator,
        protected CompanySettingLogoStorage $logoStorage,
        protected MobileNumberNormalizer $mobileNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CompanySetting
    {
        PermissionChecker::authorizeAbility(fn (): bool => CompanySettingAuthorization::canCreate());

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): CompanySetting {
            return CompanySetting::query()->create($this->contentAttributes($data));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanySetting $setting, array $data): CompanySetting
    {
        PermissionChecker::authorizeAbility(fn (): bool => CompanySettingAuthorization::canEdit());

        $data = $this->prepareData($data, $setting);

        $this->dataValidator->validate($data, $setting);

        return DB::transaction(function () use ($setting, $data): CompanySetting {
            $setting->update($this->contentAttributes($data, $setting));

            return $setting->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?CompanySetting $setting = null): array
    {
        if (array_key_exists('name_en', $data) && is_string($data['name_en'])) {
            $data['name_en'] = trim($data['name_en']);
        }

        if (array_key_exists('name_ur', $data) && is_string($data['name_ur'])) {
            $data['name_ur'] = trim($data['name_ur']);
        }

        $data['phones'] = $this->normalizePhoneEntries($data['phones'] ?? []);
        $data['whatsapp_numbers'] = $this->normalizeWhatsAppEntries($data['whatsapp_numbers'] ?? []);
        $data['emails'] = $this->normalizeEmailList($data['emails'] ?? []);

        if (array_key_exists('website_url', $data)) {
            $data['website_url'] = $this->normalizeWebsiteUrl($data['website_url']);
        }

        if (array_key_exists('logo', $data)) {
            $newPath = $this->logoStorage->extractPath($data['logo']);

            if ($setting !== null) {
                $this->logoStorage->cleanupIfReplaced($setting->logo_path, $newPath);
            }

            $data['logo_path'] = $newPath;
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $items
     * @return list<array{contact_person: ?string, phone_number: ?string}>
     */
    protected function normalizePhoneEntries(array $items): array
    {
        return $this->normalizeContactNumberEntries(
            $items,
            'phone_number',
            ['number', 'value'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $items
     * @return list<array{contact_person: ?string, whatsapp_number: ?string}>
     */
    protected function normalizeWhatsAppEntries(array $items): array
    {
        return $this->normalizeContactNumberEntries(
            $items,
            'whatsapp_number',
            ['number', 'value'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $items
     * @param  list<string>  $legacyNumberKeys
     * @return list<array<string, ?string>>
     */
    protected function normalizeContactNumberEntries(array $items, string $numberKey, array $legacyNumberKeys = []): array
    {
        $seenNumbers = [];

        return collect($items)
            ->map(function (mixed $item) use ($numberKey, $legacyNumberKeys): ?array {
                if (is_string($item)) {
                    $contactPerson = '';
                    $number = trim($item);
                } elseif (is_array($item)) {
                    $contactPerson = trim((string) ($item['contact_person'] ?? ''));
                    $number = trim((string) ($item[$numberKey] ?? ''));

                    if ($number === '') {
                        foreach ($legacyNumberKeys as $legacyKey) {
                            $number = trim((string) ($item[$legacyKey] ?? ''));

                            if ($number !== '') {
                                break;
                            }
                        }
                    }
                } else {
                    return null;
                }

                if ($contactPerson === '' && $number === '') {
                    return null;
                }

                if ($number !== '') {
                    $number = $this->mobileNormalizer->normalize($number) ?? $number;
                }

                return [
                    'contact_person' => $contactPerson !== '' ? $contactPerson : null,
                    $numberKey => $number !== '' ? $number : null,
                ];
            })
            ->filter()
            ->filter(function (array $entry) use ($numberKey, &$seenNumbers): bool {
                $number = $entry[$numberKey] ?? null;

                if ($number === null) {
                    return true;
                }

                if (in_array($number, $seenNumbers, true)) {
                    return false;
                }

                $seenNumbers[] = $number;

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $items
     * @return list<string>
     */
    protected function normalizeEmailList(array $items): array
    {
        return collect($items)
            ->map(function (mixed $item): ?string {
                $value = is_array($item)
                    ? (string) ($item['email'] ?? $item['value'] ?? '')
                    : (string) $item;

                $value = strtolower(trim($value));

                return $value !== '' ? $value : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, ?CompanySetting $setting = null): array
    {
        $attributes = [
            'name_en' => $data['name_en'],
            'name_ur' => filled($data['name_ur'] ?? null) ? $data['name_ur'] : '',
            'address_en' => filled($data['address_en'] ?? null) ? $data['address_en'] : null,
            'address_ur' => filled($data['address_ur'] ?? null) ? $data['address_ur'] : null,
            'phones' => $data['phones'] ?? [],
            'whatsapp_numbers' => $data['whatsapp_numbers'] ?? [],
            'emails' => $data['emails'] ?? [],
            'website_url' => filled($data['website_url'] ?? null) ? $data['website_url'] : null,
            'ntn' => filled($data['ntn'] ?? null) ? $data['ntn'] : null,
            'strn' => filled($data['strn'] ?? null) ? $data['strn'] : null,
            'currency' => $data['currency'] ?? 'PKR',
            'decimal_places' => (int) ($data['decimal_places'] ?? 0),
            'timezone' => $data['timezone'] ?? 'Asia/Karachi',
        ];

        if (array_key_exists('logo_path', $data)) {
            $attributes['logo_path'] = $data['logo_path'];
        }

        return $attributes;
    }

    protected function normalizeWebsiteUrl(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $website = trim((string) $value);
        $website = preg_replace('#^https?://#i', '', $website) ?? $website;
        $website = trim($website, " \t\n\r\0\x0B/");

        if ($website === '') {
            return null;
        }

        return 'https://' . $website;
    }
}

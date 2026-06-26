<?php

namespace App\Services\Settings;

use App\Models\CompanySetting;

class CompanySettingResolver
{
    protected ?CompanySetting $cached = null;

    public function __construct(
        protected CompanySettingLogoStorage $logoStorage,
    ) {}

    public function settings(): ?CompanySetting
    {
        return $this->cached ??= CompanySetting::query()->first();
    }

    public function isConfigured(): bool
    {
        return $this->settings() !== null;
    }

    public function logoUrl(): ?string
    {
        return $this->logoStorage->url($this->settings()?->logo_path);
    }

    public function nameEn(): string
    {
        return (string) ($this->settings()?->name_en ?? config('agricart.brand.name', 'Agricart ERP'));
    }

    public function nameUr(): string
    {
        return (string) ($this->settings()?->name_ur ?? '');
    }

    public function addressEn(): ?string
    {
        $value = $this->settings()?->address_en;

        return filled($value) ? (string) $value : null;
    }

    public function addressUr(): ?string
    {
        $value = $this->settings()?->address_ur;

        return filled($value) ? (string) $value : null;
    }

    /**
     * @return list<array{contact_person: ?string, phone_number: ?string}>
     */
    public function phones(): array
    {
        $phones = $this->settings()?->phones;

        return is_array($phones) ? $phones : [];
    }

    /**
     * @return list<array{contact_person: ?string, whatsapp_number: ?string}>
     */
    public function whatsappNumbers(): array
    {
        $numbers = $this->settings()?->whatsapp_numbers;

        return is_array($numbers) ? $numbers : [];
    }

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        $emails = $this->settings()?->emails;

        return is_array($emails) ? array_values(array_filter($emails, fn (mixed $email): bool => filled($email))) : [];
    }

    public function primaryPhone(): ?string
    {
        foreach ($this->phones() as $phone) {
            $number = $phone['phone_number'] ?? null;

            if (filled($number)) {
                return (string) $number;
            }
        }

        return null;
    }

    public function websiteUrl(): ?string
    {
        $value = $this->settings()?->website_url;

        return filled($value) ? (string) $value : null;
    }

    public function ntn(): ?string
    {
        $value = $this->settings()?->ntn;

        return filled($value) ? (string) $value : null;
    }

    public function strn(): ?string
    {
        $value = $this->settings()?->strn;

        return filled($value) ? (string) $value : null;
    }

    public function currency(): string
    {
        return (string) ($this->settings()?->currency ?? 'PKR');
    }

    public function decimalPlaces(): int
    {
        return (int) ($this->settings()?->decimal_places ?? 0);
    }

    public function timezone(): string
    {
        return (string) ($this->settings()?->timezone ?? config('app.timezone', 'Asia/Karachi'));
    }

    /**
     * Shared company block for purchase invoices, sales invoices, POS receipts, quotations, and reports.
     *
     * @return array{
     *     logo_url: ?string,
     *     name_en: string,
     *     name_ur: string,
     *     address_en: ?string,
     *     address_ur: ?string,
     *     phones: list<array{contact_person: ?string, phone_number: ?string}>,
     *     whatsapp_numbers: list<array{contact_person: ?string, whatsapp_number: ?string}>,
     *     emails: list<string>,
     *     primary_phone: ?string,
     *     website_url: ?string,
     *     ntn: ?string,
     *     strn: ?string,
     *     currency: string,
     *     decimal_places: int,
     *     timezone: string,
     * }
     */
    public function documentProfile(): array
    {
        return [
            'logo_url' => $this->logoUrl(),
            'name_en' => $this->nameEn(),
            'name_ur' => $this->nameUr(),
            'address_en' => $this->addressEn(),
            'address_ur' => $this->addressUr(),
            'phones' => $this->phones(),
            'whatsapp_numbers' => $this->whatsappNumbers(),
            'emails' => $this->emails(),
            'primary_phone' => $this->primaryPhone(),
            'website_url' => $this->websiteUrl(),
            'ntn' => $this->ntn(),
            'strn' => $this->strn(),
            'currency' => $this->currency(),
            'decimal_places' => $this->decimalPlaces(),
            'timezone' => $this->timezone(),
        ];
    }
}

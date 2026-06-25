<?php

namespace App\Services\Contacts;

use App\Models\ContactMobileNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SupplierMobileRegistry
{
    public function __construct(
        protected MobileNumberNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, array{raw: string, category: string, contact_person_id?: int|null}>  $entries
     */
    public function assertUnique(array $entries, ?int $excludeSupplierId = null): void
    {
        $normalizedValues = [];

        foreach ($entries as $entry) {
            $normalized = $this->normalizer->normalize($entry['raw']);

            if ($normalized === null) {
                continue;
            }

            if (in_array($normalized, $normalizedValues, true)) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('This mobile number is already used on this form.'),
                ]);
            }

            $normalizedValues[] = $normalized;

            $exists = ContactMobileNumber::query()
                ->where('mobile_normalized', $normalized)
                ->where('contactable_type', ContactMobileNumber::CONTACTABLE_SUPPLIER)
                ->when($excludeSupplierId !== null, function ($query) use ($excludeSupplierId): void {
                    $query->where('contactable_id', '!=', $excludeSupplierId);
                })
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('This mobile number already exists for another supplier.'),
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{raw: string, category: string, contact_person_id?: int|null}>  $entries
     */
    public function syncForSupplier(int $supplierId, array $entries): void
    {
        ContactMobileNumber::query()
            ->where('contactable_type', ContactMobileNumber::CONTACTABLE_SUPPLIER)
            ->where('contactable_id', $supplierId)
            ->delete();

        foreach ($entries as $entry) {
            $normalized = $this->normalizer->normalize($entry['raw']);

            if ($normalized === null) {
                continue;
            }

            try {
                ContactMobileNumber::query()->create([
                    'mobile_normalized' => $normalized,
                    'contactable_type' => ContactMobileNumber::CONTACTABLE_SUPPLIER,
                    'contactable_id' => $supplierId,
                    'category' => $entry['category'],
                    'contact_person_id' => $entry['contact_person_id'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('This mobile number already exists for another supplier.'),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{raw: string, category: string}>
     */
    public function collectEntriesFromFormData(array $data): array
    {
        $entries = [];

        if (filled($data['mobile_number'] ?? null)) {
            $entries[] = [
                'raw' => (string) $data['mobile_number'],
                'category' => ContactMobileNumber::CATEGORY_PRIMARY,
            ];
        }

        foreach ($data['additional_contacts'] ?? [] as $contact) {
            if (blank($contact['mobile_number'] ?? null)) {
                continue;
            }

            $entries[] = [
                'raw' => (string) $contact['mobile_number'],
                'category' => ContactMobileNumber::CATEGORY_ADDITIONAL,
            ];
        }

        return $entries;
    }
}

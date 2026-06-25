<?php

namespace App\Services\Settings;

use App\Models\Tax;
use App\Support\Settings\TaxAuthorization;
use Illuminate\Support\Facades\DB;

class TaxPersistenceService
{
    public function __construct(
        protected TaxDataValidator $dataValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Tax
    {
        if (! TaxAuthorization::canCreate()) {
            abort(403);
        }

        $data = $this->prepareData($data);
        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): Tax {
            return Tax::query()->create($this->attributes($data));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tax $tax, array $data): Tax
    {
        if (! TaxAuthorization::canEdit()) {
            abort(403);
        }

        $data = $this->prepareData($data);
        $this->dataValidator->validate($data, $tax);

        return DB::transaction(function () use ($tax, $data): Tax {
            $tax->update($this->attributes($data));

            return $tax->fresh();
        });
    }

    public function delete(Tax $tax): void
    {
        if (! TaxAuthorization::canDelete()) {
            abort(403);
        }

        $tax->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data): array
    {
        if (array_key_exists('name', $data) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('code', $data)) {
            $code = trim((string) ($data['code'] ?? ''));
            $data['code'] = $code !== '' ? strtoupper($code) : null;
        }

        $data['apply_on'] = collect($data['apply_on'] ?? [])
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values()
            ->all();

        $data['status'] = (string) ($data['status'] ?? Tax::STATUS_ACTIVE);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'],
            'rate_value' => $data['rate_value'],
            'apply_on' => $data['apply_on'],
            'status' => $data['status'],
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
        ];
    }
}

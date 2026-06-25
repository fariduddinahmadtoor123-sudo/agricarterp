<?php

namespace App\Services\Settings;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AiSettingPersistenceService
{
    public function __construct(
        protected AiSettingDataValidator $dataValidator,
        protected AiSettingResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): AiSetting
    {
        $setting = $this->resolver->record();
        $data = $this->prepareData($data, $setting);

        $this->dataValidator->validate($data, $setting);

        return DB::transaction(function () use ($setting, $data): AiSetting {
            $setting->update($this->contentAttributes($data, $setting));

            return $setting->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, AiSetting $setting): array
    {
        $data['enrichment_enabled'] = (bool) ($data['enrichment_enabled'] ?? true);
        $data['batch_limit'] = max(1, (int) ($data['batch_limit'] ?? 50));

        if (blank($data['openrouter_api_key'] ?? null) && filled($setting->openrouter_api_key)) {
            $data['keep_existing_api_key'] = true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, AiSetting $setting): array
    {
        $attributes = [
            'vision_model' => $data['vision_model'],
            'enrichment_enabled' => $data['enrichment_enabled'],
            'batch_limit' => $data['batch_limit'],
        ];

        if (filled($data['openrouter_api_key'] ?? null)) {
            $attributes['openrouter_api_key'] = Crypt::encryptString((string) $data['openrouter_api_key']);
        } elseif (! ($data['keep_existing_api_key'] ?? false)) {
            $attributes['openrouter_api_key'] = null;
        }

        return $attributes;
    }
}

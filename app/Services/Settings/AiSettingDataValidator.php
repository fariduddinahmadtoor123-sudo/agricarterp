<?php

namespace App\Services\Settings;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AiSettingDataValidator
{
    public function __construct(
        protected AiSettingResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?AiSetting $setting = null): void
    {
        $validator = Validator::make($data, [
            'openrouter_api_key' => ['nullable', 'string', 'max:500'],
            'vision_model' => ['required', 'string', 'max:120'],
            'enrichment_enabled' => ['boolean'],
            'batch_limit' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $hasIncomingKey = filled($data['openrouter_api_key'] ?? null);
        $hasSavedKey = $setting !== null && filled($setting->openrouter_api_key);

        if (! $hasIncomingKey && ! $hasSavedKey && blank(config('ai.openrouter.api_key'))) {
            throw ValidationException::withMessages([
                'openrouter_api_key' => 'OpenRouter API key is required.',
            ]);
        }
    }
}

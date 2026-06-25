<?php

namespace App\Services\ProductCatalog;

use App\Models\Unit;
use App\Rules\UniqueUnitAbbreviation;
use App\Rules\UniqueUnitEnglishName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?Unit $unit = null): void
    {
        $validator = Validator::make($data, [
            'name_en' => ['required', 'string', 'max:100', new UniqueUnitEnglishName($unit)],
            'abbreviation_en' => ['required', 'string', 'max:20', new UniqueUnitAbbreviation($unit)],
            'unit_type' => [
                'required',
                Rule::in([
                    Unit::TYPE_WEIGHT,
                    Unit::TYPE_VOLUME,
                    Unit::TYPE_LENGTH,
                    Unit::TYPE_AREA,
                    Unit::TYPE_COUNT,
                    Unit::TYPE_PACKAGING,
                ]),
            ],
            'name_ur' => ['nullable', 'string', 'max:100'],
            'abbreviation_ur' => ['nullable', 'string', 'max:20'],
            'usage_notes' => ['nullable', 'string'],
            'ai_status' => [
                'nullable',
                Rule::in([
                    Unit::AI_STATUS_PENDING,
                    Unit::AI_STATUS_PROCESSING,
                    Unit::AI_STATUS_COMPLETE,
                    Unit::AI_STATUS_REVIEW,
                    Unit::AI_STATUS_FAILED,
                ]),
            ],
            'ai_generated_at' => ['nullable', 'date'],
            'ai_version' => ['nullable', 'string', 'max:50'],
            'status' => [
                'nullable',
                Rule::in([Unit::STATUS_ACTIVE, Unit::STATUS_ARCHIVED]),
            ],
            'is_standard' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}

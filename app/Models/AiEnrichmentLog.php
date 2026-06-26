<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiEnrichmentLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'subject_label',
        'status',
        'model',
        'message',
        'error_code',
        'error_reason',
        'suggested_action',
        'raw_response',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function adminSummary(): string
    {
        if ($this->status === self::STATUS_SUCCESS) {
            return (string) ($this->message ?? 'Enrichment completed.');
        }

        if (filled($this->error_reason)) {
            $summary = 'Reason: ' . $this->error_reason;

            if (filled($this->suggested_action)) {
                $summary .= ' Suggested action: ' . $this->suggested_action;
            }

            return $summary;
        }

        return (string) ($this->message ?? 'AI enrichment failed.');
    }

    public static function latestFailureSummaryFor(string $subjectType, int $subjectId): ?string
    {
        $log = self::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', self::STATUS_FAILED)
            ->latest('id')
            ->first();

        return $log?->adminSummary();
    }
}

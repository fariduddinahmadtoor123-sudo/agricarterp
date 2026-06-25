<div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Time</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ $log->created_at?->toDateTimeString() }}</div>
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ ucfirst($log->status) }}</div>
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ $log->subject_label }}</div>
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Model</div>
            <div class="mt-1 text-gray-950 dark:text-white">{{ $log->model ?: '—' }}</div>
        </div>
    </div>

    @if ($log->status === \App\Models\AiEnrichmentLog::STATUS_FAILED)
        <div class="rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-500/30 dark:bg-danger-500/10">
            <div class="text-xs font-medium uppercase tracking-wide text-danger-700 dark:text-danger-300">Reason</div>
            <div class="mt-1 text-danger-950 dark:text-danger-100">{{ $log->error_reason ?: $log->message }}</div>

            @if (filled($log->suggested_action))
                <div class="mt-4 text-xs font-medium uppercase tracking-wide text-danger-700 dark:text-danger-300">Suggested Action</div>
                <div class="mt-1 text-danger-950 dark:text-danger-100">{{ $log->suggested_action }}</div>
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">HTTP / Error Code</div>
                <div class="mt-1 font-mono text-gray-950 dark:text-white">{{ filled($log->error_code) ? 'HTTP ' . $log->error_code : '—' }}</div>
            </div>
        </div>

        @if (filled($log->raw_response))
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Raw Response</div>
                <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $log->raw_response }}</pre>
            </div>
        @endif
    @else
        <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
            <div class="text-success-950 dark:text-success-100">{{ $log->message ?: 'Enrichment completed.' }}</div>
        </div>
    @endif
</div>

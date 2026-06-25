<div class="fi-ta-ctn divide-y divide-gray-200 dark:divide-white/10">
    @forelse ($logs as $log)
        <div class="px-1 py-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <span class="font-medium text-gray-950 dark:text-white">{{ $log->created_at?->toDateTimeString() }}</span>
                <span @class([
                    'fi-badge fi-size-sm',
                    'fi-color-success' => $log->level === 'info',
                    'fi-color-warning' => $log->level === 'warning',
                    'fi-color-danger' => $log->level === 'error',
                ])>{{ strtoupper($log->level) }}</span>
            </div>
            @if (filled($log->step))
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $log->step }}</div>
            @endif
            <div class="mt-1 text-gray-700 dark:text-gray-300">{{ $log->message }}</div>
        </div>
    @empty
        <div class="py-6 text-sm text-gray-500 dark:text-gray-400">No log entries yet.</div>
    @endforelse
</div>

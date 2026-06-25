@php
    use App\Models\BackupLog;
    use App\Models\RestoreRun;

    $restoreRuns = RestoreRun::query()->with('logs')->latest('id')->limit(20)->get();
@endphp

<div class="fi-ta-ctn divide-y divide-gray-200 dark:divide-white/10">
    @forelse ($restoreRuns as $run)
        <div class="px-1 py-4">
            <div class="flex items-center justify-between gap-3">
                <span class="font-medium text-gray-950 dark:text-white">{{ $run->created_at?->toDateTimeString() }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-300">{{ strtoupper($run->status) }}</span>
            </div>
            @if (filled($run->error_message))
                <div class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $run->error_message }}</div>
            @endif
            <div class="mt-3 space-y-2">
                @foreach ($run->logs as $log)
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('H:i:s') }}</span>
                        — {{ $log->message }}
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="py-6 text-sm text-gray-500 dark:text-gray-400">No restore activity yet.</div>
    @endforelse
</div>

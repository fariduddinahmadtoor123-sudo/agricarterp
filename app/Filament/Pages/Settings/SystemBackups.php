<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Backup;
use App\Models\BackupLog;
use App\Models\BackupSchedule;
use App\Models\RestoreRun;
use App\Services\Backup\BackupDispatchService;
use App\Services\Backup\BackupFileNameGenerator;
use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\Storage\GoogleDriveBackupStorage;
use App\Services\Backup\Storage\LocalBackupStorage;
use App\Support\Settings\BackupAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemBackups extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/system-backups';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'system-backups';
    }

    public static function canAccess(): bool
    {
        return BackupAuthorization::canView();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Backup::query()->latest('id'))
            ->defaultSort('id', 'desc')
            ->modelLabel('Backup')
            ->pluralModelLabel('Backups')
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Create a full backup of your database, product images, categories, settings, and uploads.')
            ->poll('10s')
            ->headerActions($this->getBackupHeaderActions())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('file_name')
                    ->label('File')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Backup::STATUS_COMPLETED => 'success',
                        Backup::STATUS_RUNNING, Backup::STATUS_PENDING => 'warning',
                        Backup::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('trigger')
                    ->label('Trigger')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('file_size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $this->formatBytes($state ?? 0)),
                TextColumn::make('destinations')
                    ->label('Destinations')
                    ->state(function (Backup $record): string {
                        $parts = [];

                        if ($record->local_path) {
                            $parts[] = 'Local';
                        }

                        if ($record->google_drive_file_id) {
                            $parts[] = 'Google Drive';
                        }

                        return $parts === [] ? '—' : implode(', ', $parts);
                    }),
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                $this->getDownloadBackupAction(),
                $this->getRetryBackupAction(),
                $this->getViewBackupLogsAction(),
                $this->getRestoreFromBackupAction(),
                $this->getDeleteBackupAction(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getBackupHeaderActions(): array
    {
        $actions = [];

        if (BackupAuthorization::canCreate()) {
            $actions[] = $this->getCreateBackupAction();
        }

        if (BackupAuthorization::canRestore()) {
            $actions[] = $this->getRestoreUploadAction();
        }

        if (BackupAuthorization::canManageSchedules()) {
            $actions[] = $this->getManageSchedulesAction();
            $actions[] = $this->getViewRestoreLogsAction();
        }

        return $actions;
    }

    protected function getCreateBackupAction(): Action
    {
        $googleDrive = app(GoogleDriveBackupStorage::class);

        return Action::make('createBackup')
            ->label('Create Backup Now')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Create Full Backup')
            ->modalDescription('Database, product images, categories, brands, units, SEO content, settings, and uploads are included. Processing runs in the background queue.')
            ->modalSubmitActionLabel('Start Backup')
            ->schema([
                CheckboxList::make('destinations')
                    ->label('Storage Destinations')
                    ->options([
                        'local' => 'Local Server',
                        'google_drive' => 'Google Drive' . ($googleDrive->isConfigured() ? '' : ' (not configured)'),
                    ])
                    ->default(['local'])
                    ->required()
                    ->columns(1),
            ])
            ->action(function (array $data): void {
                $destinations = array_values($data['destinations'] ?? ['local']);

                if ($destinations === []) {
                    Notification::make()
                        ->danger()
                        ->title('Select at least one storage destination.')
                        ->send();

                    return;
                }

                if (in_array('google_drive', $destinations, true) && ! app(GoogleDriveBackupStorage::class)->isConfigured()) {
                    Notification::make()
                        ->danger()
                        ->title('Google Drive is not configured. Add service account settings in .env first.')
                        ->send();

                    return;
                }

                $backup = Backup::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'type' => Backup::TYPE_FULL,
                    'status' => Backup::STATUS_PENDING,
                    'trigger' => Backup::TRIGGER_MANUAL,
                    'file_name' => BackupFileNameGenerator::next(),
                    'manifest_version' => (string) config('backup.format_version', '1.0'),
                    'modules_included' => config('backup.modules', []),
                    'created_by_user_id' => auth()->id(),
                ]);

                app(BackupDispatchService::class)->dispatchCreate($backup, $destinations);

                Notification::make()
                    ->success()
                    ->title('Backup started')
                    ->body('The backup is running in the background. This page will update automatically.')
                    ->send();
            });
    }

    protected function getRestoreUploadAction(): Action
    {
        return Action::make('restoreUpload')
            ->label('Restore from ZIP')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('danger')
            ->modalHeading('Restore from Backup ZIP')
            ->modalDescription('This replaces current database and uploaded files after validation. A pre-restore snapshot is created for rollback if restore fails.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Start Restore')
            ->schema([
                FileUpload::make('archive')
                    ->label('Backup ZIP')
                    ->disk(config('backup.local_disk', 'local'))
                    ->directory(trim(config('backup.upload_directory', 'backups/uploads'), '/'))
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $path = is_array($data['archive'] ?? null)
                    ? ($data['archive'][0] ?? null)
                    : ($data['archive'] ?? null);

                if (blank($path)) {
                    Notification::make()
                        ->danger()
                        ->title('Upload a backup ZIP file first.')
                        ->send();

                    return;
                }

                $absolute = Storage::disk(config('backup.local_disk', 'local'))->path($path);

                $restoreRun = RestoreRun::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'mode' => RestoreRun::MODE_REPLACE,
                    'status' => RestoreRun::STATUS_PENDING,
                    'created_by_user_id' => auth()->id(),
                ]);

                app(BackupDispatchService::class)->dispatchRestore($restoreRun, uploadedArchivePath: $absolute);

                Notification::make()
                    ->warning()
                    ->title('Restore started')
                    ->body('Validation, snapshot, and full restore are running in the background queue.')
                    ->send();
            });
    }

    protected function getManageSchedulesAction(): Action
    {
        $scheduleService = app(BackupScheduleService::class);
        $existing = BackupSchedule::query()->first();

        return Action::make('manageSchedules')
            ->label('Schedules')
            ->icon(Heroicon::OutlinedClock)
            ->modalHeading('Automatic Backup Schedules')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Save Schedule')
            ->fillForm(fn (): array => [
                'name' => $existing?->name ?? 'Default Schedule',
                'frequency' => $existing?->frequency ?? BackupSchedule::FREQUENCY_DAILY,
                'cron_expression' => $existing?->cron_expression,
                'retention_count' => $existing?->retention_count ?? 7,
                'destinations' => $existing?->destinations ?? ['local'],
                'enabled' => $existing?->enabled ?? false,
            ])
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('frequency')
                    ->options($scheduleService->frequencyOptions())
                    ->required()
                    ->native(false),
                TextInput::make('cron_expression')
                    ->label('Custom Cron Expression')
                    ->helperText('Used only when frequency is Custom.')
                    ->placeholder('0 2 * * *'),
                TextInput::make('retention_count')
                    ->label('Keep Last Backups')
                    ->numeric()
                    ->minValue(1)
                    ->default(7)
                    ->required(),
                CheckboxList::make('destinations')
                    ->options($scheduleService->destinationOptions())
                    ->columns(1)
                    ->required(),
                Toggle::make('enabled')
                    ->label('Enable automatic backups'),
            ])
            ->action(function (array $data) use ($existing): void {
                $payload = [
                    'name' => $data['name'],
                    'frequency' => $data['frequency'],
                    'cron_expression' => $data['cron_expression'] ?? null,
                    'retention_count' => (int) ($data['retention_count'] ?? 7),
                    'destinations' => array_values($data['destinations'] ?? ['local']),
                    'enabled' => (bool) ($data['enabled'] ?? false),
                    'next_run_at' => ($data['enabled'] ?? false) ? now() : null,
                ];

                if ($existing !== null) {
                    $existing->update($payload);
                } else {
                    BackupSchedule::query()->create($payload);
                }

                Notification::make()
                    ->success()
                    ->title('Backup schedule saved')
                    ->send();
            });
    }

    protected function getViewRestoreLogsAction(): Action
    {
        return Action::make('viewRestoreLogs')
            ->label('Restore Logs')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->modalHeading('Restore Logs')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (): \Illuminate\Contracts\View\View => view('filament.settings.backup-restore-logs'));
    }

    protected function getDownloadBackupAction(): Action
    {
        return Action::make('downloadBackup')
            ->label('Download')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (Backup $record): bool => BackupAuthorization::canDownload() && $record->isCompleted() && filled($record->local_path))
            ->url(fn (Backup $record): string => route('filament.admin.backups.download', $record))
            ->openUrlInNewTab();
    }

    protected function getRetryBackupAction(): Action
    {
        return Action::make('retryBackup')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Backup $record): bool => BackupAuthorization::canCreate()
                && in_array($record->status, [Backup::STATUS_PENDING, Backup::STATUS_FAILED], true))
            ->requiresConfirmation()
            ->modalHeading('Retry Backup')
            ->modalDescription('The backup will run again in the background. If a previous attempt never started, this will process it now.')
            ->action(function (Backup $record): void {
                $destinations = ['local'];

                if (app(GoogleDriveBackupStorage::class)->isConfigured()) {
                    $destinations[] = 'google_drive';
                }

                $record->update([
                    'status' => Backup::STATUS_PENDING,
                    'error_message' => null,
                    'started_at' => null,
                    'completed_at' => null,
                ]);

                app(BackupDispatchService::class)->dispatchCreate($record, $destinations);

                Notification::make()
                    ->success()
                    ->title('Backup restarted')
                    ->body('Processing will begin automatically. Refresh this page in a moment.')
                    ->send();
            });
    }

    protected function getViewBackupLogsAction(): Action
    {
        return Action::make('viewBackupLogs')
            ->label('Logs')
            ->icon(Heroicon::OutlinedDocumentText)
            ->modalHeading('Backup Logs')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (Backup $record): \Illuminate\Contracts\View\View => view('filament.settings.backup-logs', [
                'logs' => $record->logs()->latest('id')->limit(200)->get(),
            ]));
    }

    protected function getRestoreFromBackupAction(): Action
    {
        return Action::make('restoreBackup')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->visible(fn (Backup $record): bool => BackupAuthorization::canRestore() && $record->isCompleted() && filled($record->local_path))
            ->requiresConfirmation()
            ->modalHeading('Restore This Backup')
            ->modalDescription('Current database and uploads will be replaced after validation. A pre-restore snapshot is created for rollback if restore fails.')
            ->modalSubmitActionLabel('Start Restore')
            ->action(function (Backup $record): void {
                $restoreRun = RestoreRun::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'backup_id' => $record->id,
                    'mode' => RestoreRun::MODE_REPLACE,
                    'status' => RestoreRun::STATUS_PENDING,
                    'created_by_user_id' => auth()->id(),
                ]);

                app(BackupDispatchService::class)->dispatchRestore($restoreRun, backupId: $record->id);

                Notification::make()
                    ->warning()
                    ->title('Restore started')
                    ->body('The restore is running in the background queue.')
                    ->send();
            });
    }

    protected function getDeleteBackupAction(): Action
    {
        return Action::make('deleteBackup')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => BackupAuthorization::canDelete())
            ->requiresConfirmation()
            ->action(function (Backup $record): void {
                if ($record->local_path) {
                    app(LocalBackupStorage::class)->delete($record->local_path);
                }

                $record->delete();

                Notification::make()
                    ->success()
                    ->title('Backup deleted')
                    ->send();
            });
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Backups';
    }
}

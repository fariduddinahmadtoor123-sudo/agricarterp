<?php

namespace App\Filament\Pages\Approvals\Staff;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use App\Filament\Users\Schemas\UserForm;
use App\Models\Role;
use App\Models\UserApplication;
use App\Services\Users\UserApplicationApprovalService;
use App\Support\Users\UserApplicationAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Users extends Page implements HasTable
{
    use InteractsWithApprovalPage;
    use InteractsWithTable;

    protected static ?string $slug = 'approvals/staff/users';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'staff';
    }

    public static function typeKey(): ?string
    {
        return 'users';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'agricart-approvals-staff-users-list'])
            ->query(UserApplication::query())
            ->defaultSort('created_at', 'desc')
            ->modelLabel('Application')
            ->pluralModelLabel('Staff User Applications')
            ->emptyStateHeading('No applications')
            ->emptyStateDescription('Public staff registration submissions will appear here for review.')
            ->columns([
                TextColumn::make('application_number')
                    ->label('Application #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('users.application_statuses')[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        UserApplication::STATUS_PENDING => 'warning',
                        UserApplication::STATUS_APPROVED => 'success',
                        UserApplication::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(config('users.application_statuses', []))
                    ->default(UserApplication::STATUS_PENDING),
            ])
            ->recordActions([
                $this->getViewApplicationAction(),
                $this->getApproveApplicationAction(),
                $this->getRejectApplicationAction(),
            ]);
    }

    protected function getViewApplicationAction(): Action
    {
        return Action::make('viewApplication')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => UserApplicationAuthorization::canView())
            ->modalHeading('View Application')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (UserApplication $record): array => UserForm::fromApplication($record))
            ->schema(fn (Schema $schema): Schema => UserForm::configure($schema, readOnly: true));
    }

    protected function getApproveApplicationAction(): Action
    {
        return Action::make('approveApplication')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (UserApplication $record): bool => UserApplicationAuthorization::canApprove() && $record->isPending())
            ->modalHeading('Approve Staff Application')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Approve & Create User')
            ->schema([
                Select::make('role_id')
                    ->label('Assign Role')
                    ->options(fn (): array => Role::query()
                        ->where('slug', '!=', Role::SLUG_SUPER_ADMIN)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, UserApplication $record, UserApplicationApprovalService $approval): void {
                try {
                    $approval->approve($record, (int) $data['role_id'], auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?? 'Validation failed')
                        ->send();

                    throw $exception;
                }

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Application approved')
                    ->body('The user can now sign in with their email and password.')
                    ->send();
            });
    }

    protected function getRejectApplicationAction(): Action
    {
        return Action::make('rejectApplication')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (UserApplication $record): bool => UserApplicationAuthorization::canReject() && $record->isPending())
            ->modalHeading('Reject Application')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Reject')
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, UserApplication $record, UserApplicationApprovalService $approval): void {
                try {
                    $approval->reject($record, (string) $data['rejection_reason'], auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?? 'Validation failed')
                        ->send();

                    throw $exception;
                }

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Application rejected')
                    ->send();
            });
    }
}

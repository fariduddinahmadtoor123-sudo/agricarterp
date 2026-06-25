<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\AiEnrichmentLog;
use App\Support\Settings\AiSettingAuthorization;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AiEnrichmentLogs extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/ai-enrichment-logs';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'ai-enrichment-logs';
    }

    public static function canAccess(): bool
    {
        return AiSettingAuthorization::canView();
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
            ->query(AiEnrichmentLog::query()->latest('id'))
            ->defaultSort('id', 'desc')
            ->modelLabel('AI Log')
            ->pluralModelLabel('AI Enrichment Logs')
            ->emptyStateHeading('No AI logs yet')
            ->emptyStateDescription('When enrichment runs, success and error details will appear here.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('subject_label')
                    ->label('Item')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AiEnrichmentLog::STATUS_SUCCESS => 'success',
                        AiEnrichmentLog::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('model')
                    ->label('Model')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->wrap()
                    ->searchable(),
            ]);
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'AI Enrichment Logs';
    }
}

<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Settings\Schemas\AiSettingForm;
use App\Services\Settings\AiSettingPersistenceService;
use App\Services\Settings\AiSettingResolver;
use App\Support\Settings\AiSettingAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class AiConfiguration extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'settings/ai-configuration';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'ai-configuration';
    }

    public static function canAccess(): bool
    {
        return AiSettingAuthorization::canView();
    }

    public function content(Schema $schema): Schema
    {
        $resolver = app(AiSettingResolver::class);
        $modelOptions = $resolver->visionModelOptions();

        return $schema
            ->components([
                Section::make('AI Settings')
                    ->description('OpenRouter powers background enrichment for product and category fields (Urdu names, descriptions, HS codes, and more).')
                    ->schema([
                        Text::make(
                            'API key: '
                            . ($resolver->hasApiKey() ? 'Saved securely' : 'Not saved yet — click Edit AI Settings to add your key.'),
                        ),
                        Text::make(
                            'Vision model: '
                            . ($modelOptions[$resolver->visionModel()] ?? $resolver->visionModel()),
                        ),
                        Text::make(
                            'Enrichment: ' . ($resolver->isEnabled() ? 'Enabled' : 'Disabled'),
                        ),
                        Text::make(
                            'Records per run: ' . $resolver->batchLimit(),
                        ),
                        Text::make(
                            'To fill empty product fields, open Product Catalog → Products and click Run AI Enrichment.',
                        ),
                        Text::make(
                            'If something fails, open Settings → AI Enrichment Logs to see the exact error message.',
                        ),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (! AiSettingAuthorization::canEdit()) {
            return [];
        }

        return [
            $this->getEditAiSettingsAction(),
        ];
    }

    protected function getEditAiSettingsAction(): Action
    {
        return Action::make('editAiSettings')
            ->label('Edit AI Settings')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('AI Settings')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (): array => AiSettingForm::defaultState())
            ->schema(fn (Schema $schema): Schema => AiSettingForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistAiSettings($schema);
            });
    }

    protected function persistAiSettings(Schema $schema): void
    {
        abort_unless(AiSettingAuthorization::canEdit(), 403);

        try {
            app(AiSettingPersistenceService::class)->save($schema->getState());
            app(\App\Services\Ai\OpenRouterModelCatalog::class)->clearCache();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Validation failed')
                ->send();

            throw $exception;
        }

        Notification::make()
            ->success()
            ->title('AI settings saved')
            ->send();

        $this->redirect(static::getUrl());
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'AI Settings';
    }
}

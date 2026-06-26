<?php

namespace App\Filament\Pages\OnlineStore;

use App\Filament\OnlineStore\Schemas\StoreFrontSettingsForm;
use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\OnlineStore\StoreFrontSettingsPersistenceService;
use App\Services\OnlineStore\StoreFrontSettingsResolver;
use App\Services\OnlineStore\StorePageLinkResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ThemeSettings extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'online-store/theme-settings';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function moduleKey(): string
    {
        return 'online-store';
    }

    public static function submenuKey(): string
    {
        return 'theme-settings';
    }

    public function mount(): void
    {
        $this->data = app(StoreFrontSettingsResolver::class)->formState();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make()
                ->schema(fn (Schema $form): Schema => StoreFrontSettingsForm::configure($form))
                ->statePath('data')
                ->livewireSubmitHandler('saveSettings'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveSettings')
                ->label('Save changes')
                ->action('saveSettings'),
        ];
    }

    public function saveSettings(): void
    {
        $data = $this->resolveSettingsFormData();

        try {
            app(StoreFrontSettingsPersistenceService::class)->save($data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Validation failed')
                ->send();

            throw $exception;
        }

        $this->data = app(StoreFrontSettingsResolver::class)->formState();

        $draftTitles = app(StorePageLinkResolver::class)->draftTitlesInLinkGroups(
            $data['header_navigation'] ?? [],
            $data['footer_quick_links'] ?? [],
            $data['footer_legal_links'] ?? [],
        );

        if ($draftTitles !== []) {
            Notification::make()
                ->warning()
                ->title('Some linked pages are still drafts')
                ->body('They are saved here but will not appear on the storefront until you publish them from Online Store → Pages: ' . implode(', ', $draftTitles))
                ->persistent()
                ->send();
        }

        Notification::make()
            ->success()
            ->title('Store front settings saved')
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSettingsFormData(): array
    {
        $state = $this->getSchema('content')->getState();

        $data = is_array($state['data'] ?? null) ? $state['data'] : $state;

        unset($data['_footer_logo_preview']);

        return $data;
    }
}

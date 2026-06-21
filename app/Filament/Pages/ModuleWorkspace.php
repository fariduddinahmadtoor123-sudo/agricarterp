<?php

namespace App\Filament\Pages;

use App\Support\Navigation\ModulePageRegistry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ModuleWorkspace extends Page
{
    protected static ?string $slug = 'module/{module}/{submenu?}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    public string $module = '';

    public ?string $submenu = null;

    public function mount(string $module, ?string $submenu = null): void
    {
        abort_unless($this->moduleExists($module), 404);

        $pageRegistry = app(ModulePageRegistry::class);

        if ($pageRegistry->moduleHasCategories($module)) {
            $this->redirect($pageRegistry->moduleEntryUrl($module));

            return;
        }

        $submenus = $this->getModuleSubmenus($module);

        if ($submenu === null || ! array_key_exists($submenu, $submenus)) {
            $firstSubmenu = array_key_first($submenus);

            if ($firstSubmenu !== null) {
                $this->redirect(static::getUrl([
                    'module' => $module,
                    'submenu' => $firstSubmenu,
                ]));

                return;
            }
        }

        $this->module = $module;
        $this->submenu = $submenu;
    }

    public function getTitle(): string | Htmlable
    {
        return $this->getSubmenuLabel() ?? $this->getModuleLabel();
    }

    public function getModuleLabel(): string
    {
        if ($this->module === 'dashboard') {
            return config('agricart.dashboard.label', 'Dashboard');
        }

        return config("agricart.modules.{$this->module}.label", 'Module');
    }

    public function getSubmenuLabel(): ?string
    {
        if ($this->submenu === null) {
            return null;
        }

        return $this->getModuleSubmenus($this->module)[$this->submenu] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function getModuleSubmenus(string $module): array
    {
        if ($module === 'dashboard') {
            return config('agricart.dashboard.submenus', []);
        }

        return config("agricart.modules.{$module}.submenus", []);
    }

    protected function moduleExists(string $module): bool
    {
        if ($module === 'dashboard') {
            return true;
        }

        return array_key_exists($module, config('agricart.modules', []));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make($this->getTitle())
                    ->description($this->getModuleLabel() . ' workspace')
                    ->schema([
                        Text::make('Placeholder content for ' . ($this->getSubmenuLabel() ?? $this->getModuleLabel()) . '. Business functionality will be added in a future phase.'),
                    ]),
            ]);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }
}

<?php

namespace App\Filament\Pages\OnlineStore;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'online-store/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'online-store';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Phase 1 — Store CMS & Public Shell')
                ->schema([
                    Text::make('Live now: Pages, Store Front settings, public homepage shell, static pages, contact form, and footer/header navigation.'),
                    Text::make('Workflow: 1) Create & publish pages · 2) Configure Store Front (menus, footer, ticker) · 3) View the public site at /'),
                    Text::make('Next phases: Cart, checkout, customer accounts, product detail polish, and online orders sync — not in Phase 1.'),
                ]),
        ]);
    }
}

<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'settings/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make(
                    fn (): HtmlString => new HtmlString(
                        view('filament.settings.overview')->render(),
                    ),
                ),
            ]);
    }
}

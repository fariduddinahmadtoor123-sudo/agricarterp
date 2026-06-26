<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class System extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'settings/system';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'system';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make(
                    fn (): HtmlString => new HtmlString(
                        view('filament.settings.system')->render(),
                    ),
                ),
            ]);
    }
}

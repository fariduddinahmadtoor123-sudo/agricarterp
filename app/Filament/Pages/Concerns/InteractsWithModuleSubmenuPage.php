<?php

namespace App\Filament\Pages\Concerns;

use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @mixin Page
 */
trait InteractsWithModuleSubmenuPage
{
    abstract public static function moduleKey(): string;

    abstract public static function submenuKey(): string;

    public function getTitle(): string | Htmlable
    {
        return config(
            'agricart.modules.' . static::moduleKey() . '.submenus.' . static::submenuKey(),
            'Page',
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make($this->getTitle())
                    ->schema([
                        Text::make('This module is under development.'),
                    ]),
            ]);
    }

    public function getBreadcrumbs(): array
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

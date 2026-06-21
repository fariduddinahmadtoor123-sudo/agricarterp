<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    /**
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->description('Dashboard overview')
                    ->schema([
                        Text::make('Welcome to Agricart ERP. Use the sidebar to switch modules and the navigation bar above to browse sub-sections.'),
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

<?php

namespace App\Filament\Pages;

use App\Support\Dashboard\ModuleQuickLinks;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

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
        $brand = config('agricart.brand.name', 'Agricart ERP');
        $moduleCount = count(ModuleQuickLinks::all());

        return $schema
            ->components([
                Section::make('Welcome to ' . $brand)
                    ->description('Your ERP home for operations, inventory, sales, and more.')
                    ->icon(Heroicon::OutlinedHome)
                    ->schema([
                        Text::make('Phase 1 UI Foundation is complete. Use the quick links below to open any module, or browse from the sidebar. Business widgets and live data will be added in Phase 2.'),
                    ]),
                Section::make('Quick Links')
                    ->description($moduleCount . ' modules ready for development')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->schema([
                        Text::make(
                            fn (): HtmlString => new HtmlString(
                                view('filament.pages.partials.dashboard-quick-links')->render(),
                            ),
                        ),
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

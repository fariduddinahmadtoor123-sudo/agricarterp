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
trait InteractsWithApprovalPage
{
    abstract public static function categoryKey(): string;

    abstract public static function typeKey(): ?string;

    public static function moduleKey(): string
    {
        return 'approvals';
    }

    public function getTitle(): string | Htmlable
    {
        $categoryKey = static::categoryKey();
        $typeKey = static::typeKey();

        if ($typeKey === null) {
            return config(
                "agricart.modules.approvals.categories.{$categoryKey}.label",
                'Page',
            );
        }

        return config(
            "agricart.modules.approvals.categories.{$categoryKey}.types.{$typeKey}",
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

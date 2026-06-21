<?php

namespace App\Filament\Concerns;

use Filament\Schemas\Schema;

/**
 * Shared responsive form defaults for Agricart ERP and future POS screens.
 */
trait ConfiguresResponsiveForms
{
    protected function configureResponsiveForm(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->columns(1);
    }
}

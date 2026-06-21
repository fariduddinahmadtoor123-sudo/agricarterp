<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Concerns\ConfiguresResponsiveForms;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditProfile extends BaseEditProfile
{
    use ConfiguresResponsiveForms;

    protected static ?string $title = 'Profile';

    public static function isSimple(): bool
    {
        return false;
    }

    public static function getLabel(): string
    {
        return 'Profile';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $this->configureResponsiveForm($schema)
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account details')
                    ->description('Update your name and email address.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                Section::make('Password')
                    ->description('Leave blank to keep your current password.')
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return '';
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return null;
    }
}

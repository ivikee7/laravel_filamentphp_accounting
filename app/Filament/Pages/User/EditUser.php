<?php

namespace App\Filament\Pages\User;

use Filament\Pages\Page;

class EditUser extends Page
{
    protected string $view = 'filament.pages.user.edit-user';

    protected static ?string $navigationLabel = 'User Profile';

    public static function getLabel(): string
    {
        return 'User Profile';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; // or check user permissions
    }
}

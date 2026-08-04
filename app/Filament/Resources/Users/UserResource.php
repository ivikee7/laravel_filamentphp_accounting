<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }


    /**
     * Controls global visibility in the panel side-navigation menu.
     * Only returns true if the authenticated user is a static super-user.
     */
    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user && $user->isSuperUser();
    }

    /**
     * Prevents a non-super-user from guessing the record URL and creating users.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperUser() ?? false;
    }

    /**
     * Prevents a non-super-user from opening individual edit profile view states.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isSuperUser() ?? false;
    }

    /**
     * Prevents any standard user from running database record destructions.
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperUser() ?? false;
    }
}

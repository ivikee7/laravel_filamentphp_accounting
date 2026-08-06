<?php

namespace App\Filament\Resources\Teams\Resources\Users;

use App\Filament\Resources\Teams\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Teams\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Teams\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Teams\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = TeamResource::class;

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
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
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}

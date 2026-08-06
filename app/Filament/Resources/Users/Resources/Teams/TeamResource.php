<?php

namespace App\Filament\Resources\Users\Resources\Teams;

use App\Filament\Resources\Users\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Users\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Users\Resources\Teams\Schemas\TeamForm;
use App\Filament\Resources\Users\Resources\Teams\Tables\TeamsTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\Team;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = UserResource::class;

//    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return TeamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamsTable::configure($table);
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
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperUser() === true;
    }
}

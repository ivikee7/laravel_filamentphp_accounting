<?php

namespace App\Filament\Resources\Teams;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Filament\Resources\Teams\Pages\ViewTeam;
use App\Filament\Resources\Teams\Schemas\TeamForm;
use App\Filament\Resources\Teams\Schemas\TeamInfolist;
use App\Filament\Resources\Teams\Tables\TeamsTable;
use App\Models\Team;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return TeamForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TeamInfolist::configure($schema);
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
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'view' => ViewTeam::route('/{record}'),
            'edit' => EditTeam::route('/{record}/edit'),
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

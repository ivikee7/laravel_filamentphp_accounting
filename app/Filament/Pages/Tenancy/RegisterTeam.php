<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Team;
use App\Models\TeamUser;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RegisterTeam extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create Workspace';
    }

    protected static ?string $slug = 'create-workspace';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
        ]);
    }

    protected function handleRegistration(array $data): Team
    {
        $team = Team::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'owner_id' => auth()->id(),
            'is_active' => true,
        ]);

        $team->members()->attach(auth()->id(), ['role' => TeamUser::ROLE_OWNER]);

        return $team;
    }
}

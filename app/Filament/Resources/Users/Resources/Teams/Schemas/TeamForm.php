<?php

namespace App\Filament\Resources\Users\Resources\Teams\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Select::make('owner_id')
                    ->relationship('owner', 'name')
                    ->default(null),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('settings')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}

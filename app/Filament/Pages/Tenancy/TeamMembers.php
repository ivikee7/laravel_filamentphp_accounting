<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Team;
use App\Models\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class TeamMembers extends Page implements HasForms, HasInfolists, HasTable
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithTable;

    protected string $view = 'filament.pages.tenancy.team-members';

    protected static bool $shouldRegisterNavigation = false;

    public function table(Table $table): Table
    {
        return $table->query(Team::query()->whereRelation('members', 'user_id', auth()->id()))
            ->columns([
                TextColumn::make('members.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('members.email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Role')
                    ->searchable()
                    ->sortable(),
            ]);
    }
}

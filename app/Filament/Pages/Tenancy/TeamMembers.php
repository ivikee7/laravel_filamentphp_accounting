<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
        /** @var Team $currentTeam */
        $currentTeam = Filament::getTenant();

        // converts the members() BelongsToMany relationship to an Eloquent Builder instance via getQuery()
        return $table->query($currentTeam ? $currentTeam->members()->getQuery() : User::query())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        TeamUser::ROLE_OWNER => 'danger',
                        TeamUser::ROLE_ADMIN => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addMember')
                ->label('Add Member')
                ->modalHeading('Add Existing Member to Workspace')
                ->schema([
                    Select::make('user_id')
                        ->label('Select User')
                        ->placeholder('Search by name or email...')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array =>
                        User::where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->toArray()
                        )
                        ->getOptionLabelUsing(fn (string $value): ?string =>
                        User::find($value)?->name
                        ),

                    Select::make('role')
                        ->options([
                            TeamUser::ROLE_ADMIN => 'Admin',
                            TeamUser::ROLE_ACCOUNTANT => 'Accountant',
                            TeamUser::ROLE_MANAGER => 'Manager',
                            // Add other roles mapping to your TeamUser class constants here
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Team $currentTeam */
                    $currentTeam = Filament::getTenant();

                    if (!$currentTeam) {
                        return;
                    }

                    // Attaches or updates the user assignment onto the pivot table
                    $currentTeam->members()->syncWithoutDetaching([
                        $data['user_id'] => ['role' => $data['role']]
                    ]);
                }),
        ];
    }
}

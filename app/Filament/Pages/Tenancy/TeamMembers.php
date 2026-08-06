<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamMembers extends Page implements HasForms, HasInfolists, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithTable;
    use InteractsWithActions;
    use InteractsWithRecord;

    protected string $view = 'filament.pages.tenancy.team-members';

    protected static bool $shouldRegisterNavigation = false;

    public function table(Table $table): Table
    {
        /** @var Team $currentTeam */
        $currentTeam = Filament::getTenant();

        // 1. Force Eloquent to fetch pivot role entries securely
        $baseQuery = $currentTeam
            ? $currentTeam->members()->withPivot(['role'])->getQuery()
            : User::query();

        return $table
            ->query($baseQuery)
            ->columns([


                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('id')
                    ->label('Owner')
                    ->icon(fn(User $record) => $record->isOwner($currentTeam) ? 'heroicon-m-check-badge' : null)
                    ->color(fn(User $record) => $record->isOwner($currentTeam) ? 'warning' : null)
                    ->alignCenter(),

                TextColumn::make('role')
                    ->searchable()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('removeMember')
                    ->label('Remove')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        /** @var Team|null $currentTeam */
                        $currentTeam = Filament::getTenant();

                        if (!$currentTeam) {
                            return;
                        }

                        // Prevent removing the workspace owner
                        if ($record->isOwner($currentTeam)) {
                            Notification::make()
                                ->title('Cannot remove the workspace owner.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $currentTeam->members()->detach($record->id);

                        Notification::make()
                            ->title('Member removed successfully.')
                            ->success()
                            ->send();
                    }),
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
                        ->getSearchResultsUsing(function (string $search): array {
                            /** @var Team|null $currentTeam */
                            $currentTeam = Filament::getTenant();

                            // Get IDs of users who are already members of this team
                            $existingMemberIds = $currentTeam
                                ? $currentTeam->members()->pluck('users.id')->toArray()
                                : [];

                            return User::where(fn($query) => $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                            )
                                // Excludes users already in the workspace
                                ->whereNotIn('id', $existingMemberIds)
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn(User $user) => [
                                    $user->id => "{$user->name} ({$user->email})"
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function (string $value): ?string {
                            $user = User::find($value);
                            return $user ? "{$user->name} ({$user->email})" : null;
                        }),

                    Select::make('role')
                        ->options([
                            TeamUser::ROLE_ADMIN => 'Admin',
                            TeamUser::ROLE_ACCOUNTANT => 'Accountant',
                            TeamUser::ROLE_MANAGER => 'Manager',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Team|null $currentTeam */
                    $currentTeam = Filament::getTenant();

                    if (!$currentTeam) {
                        return;
                    }

                    $currentTeam->members()->syncWithoutDetaching([
                        $data['user_id'] => ['role' => $data['role']]
                    ]);

                    Notification::make()
                        ->title('Member added successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

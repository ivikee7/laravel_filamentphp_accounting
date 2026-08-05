<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditTeamProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Workspace Profile';
    }

    protected static ?string $slug = 'workspace-profile';

    public function form(Schema $schema): Schema
    {
        // 1. Get the current authenticated user's role in this active team
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();
        $currentUserRole = $team ? auth()->user()->teamRole($team) : null;

        return $schema->components([
            // Section 1: Core details
            Section::make('Workspace Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        // Only owners and admins can rename the workspace
                        ->disabled(fn () => !in_array($currentUserRole, ['owner', 'admin'])),
                    TextInput::make('slug')->disabled(),
                ])->columns(2),

            // Section 2: Team Members Management Area
            Section::make('Team Members')
                ->description('Invite or manage user memberships and roles inside this workspace.')
                ->schema([

                    Actions::make([
                        Action::make('addMember')
                            ->label('Add Member')
                            ->icon('heroicon-m-user-plus')
                            ->color('primary')
                            ->form([
                                Select::make('user_id')
                                    ->label('Select User')
                                    ->options(function () {
                                        $team = Filament::getTenant();
                                        if (! $team) return [];

                                        return User::whereDoesntHave('teams', function ($query) use ($team) {
                                            $query->where('team_id', $team->id);
                                        })->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required(),
                                Select::make('role')
                                    ->options([
                                        'admin' => 'Admin',
                                        'member' => 'Member',
                                        'accountant' => 'Accountant',
                                    ])
                                    ->required(),
                            ])
                            ->action(function (array $data) {
                                $team = Filament::getTenant();

                                if ($team) {
                                    $team->members()->attach($data['user_id'], [
                                        'role' => $data['role'],
                                    ]);

                                    Notification::make()
                                        ->title('Member added successfully')
                                        ->success()
                                        ->send();
                                }
                            }),
                    ])
                        ->alignment('end')
                        // Hide the "Add Member" button entirely if the logged-in user is not an owner or admin
                        ->hidden(fn () => !in_array($currentUserRole, ['owner', 'admin'])),

                    Repeater::make('members_list')
                        ->label('Current Workspace Members')
                        ->schema([
                            TextInput::make('name')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('email')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('pivot.role')
                                ->label('Workspace Role')
                                ->options([
                                    'owner' => 'Owner',
                                    'admin' => 'Admin',
                                    'member' => 'Member',
                                    'accountant' => 'Accountant',
                                ])
                                ->required()
                                ->disabled(function (callable $get) use ($currentUserRole) {
                                    $targetUserId = $get('id');
                                    $isMe = ((int)$targetUserId === (int)auth()->id());
                                    $targetUserRole = $get('pivot.role');

                                    // Rule 1: Non-owners cannot change any roles at all
                                    if ($currentUserRole !== 'owner') {
                                        return true;
                                    }

                                    // Rule 2: Admins are not allowed to change their own role
                                    // (Even if an Admin tries, they are blocked here)
                                    if ($isMe && $targetUserRole === 'admin') {
                                        return true;
                                    }

                                    return false;
                                }),
                        ])
                        ->columns(3)
                        ->addable(false)
                        // Configure who can be removed from the team
                        ->deletable(function (callable $get) use ($currentUserRole) {
                            $targetUserId = $get('id');
                            $isMe = ((int)$targetUserId === (int)auth()->id());
                            $targetUserRole = $get('pivot.role');

                            // Rule 3: Owners cannot be deleted/detached from the team by anyone
                            if ($targetUserRole === 'owner') {
                                return false;
                            }

                            // Rule 4: Only Owners and Admins can delete members
                            if (!in_array($currentUserRole, ['owner', 'admin'])) {
                                return false;
                            }

                            // Rule 5: Admins can remove other members, but cannot delete themselves
                            if ($currentUserRole === 'admin' && $isMe) {
                                return false;
                            }

                            return true;
                        })
                        ->deleteAction(
                            fn (Action $action) => $action->before(function (array $state, array $arguments) {
                                $team = Filament::getTenant();

                                // 1. Capture the structural row key string (e.g., 'record-0' or 'uuid')
                                $rowKey = $arguments['item'] ?? null;

                                if ($team && $rowKey && isset($state[$rowKey]['id'])) {
                                    // 2. Extract the actual database user ID linked to that row segment
                                    $userIdToDetach = $state[$rowKey]['id'];

                                    // 3. Detach the relationship from the team pivot database matrix
                                    $team->members()->detach($userIdToDetach);

                                    Notification::make()
                                        ->title('Member removed successfully')
                                        ->success()
                                        ->send();
                                }
                            })
                        ),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $team = $this->tenant ?? Filament::getTenant();

        if ($team) {
            $data['members_list'] = $team->members->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'pivot' => [
                        'role' => $user->pivot?->role,
                    ]
                ];
            })->toArray();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $team = $this->tenant ?? Filament::getTenant();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($team && isset($data['members_list'])) {
            foreach ($data['members_list'] as $memberData) {
                if (isset($memberData['id']) && isset($memberData['pivot']['role'])) {

                    $oldRole = $team->members()->where('user_id', $memberData['id'])->first()?->pivot?->role;
                    $newRole = $memberData['pivot']['role'];

                    if ($oldRole !== $newRole) {
                        // Check if the current user is a structural owner or a static super-user
                        if ($user->teamRole($team) !== 'owner') {
                            continue;
                        }

                        // Ownership Transfer Logic
                        if ($newRole === 'owner' && (int)$memberData['id'] !== (int)$user->id) {
                            // CRUCIAL: Only demote the current user if they are NOT a static super-user
                            if (!$user->isSuperUser()) {
                                $team->members()->updateExistingPivot($user->id, [
                                    'role' => 'admin',
                                ]);
                            }
                        }

                        $team->members()->updateExistingPivot($memberData['id'], [
                            'role' => $newRole,
                        ]);
                    }
                }
            }
        }

        unset($data['members_list']);

        return $data;
    }

}

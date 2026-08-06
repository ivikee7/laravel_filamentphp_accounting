<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user is a hardcoded static super-user.
     */
    public function isSuperUser(): bool
    {
        $superUsers = [
            'ivikee7@gmail.com',
        ];
        return in_array($this->email, $superUsers, true);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_users')
            ->using(TeamUser::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperUser()) {
            return \App\Models\Team::all(); // Gives them access to EVERY team dropdown
        }

        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return $this->teams()->whereKey($tenant->getKey())->exists();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamUser::class);
    }

    public function teamRole(Team|int $team): ?string
    {
        if ($this->isSuperUser()) {
            return 'owner'; // Automatically grants total management power
        }

        $teamId = $team instanceof Team ? $team->getKey() : $team;

        return $this->teamMembers()
            ->where('team_id', $teamId)
            ->value('role');
    }

    public function canManageAccounting(Team|int $team): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return in_array(
            $this->teamRole($team),
            [TeamUser::ROLE_OWNER, TeamUser::ROLE_ADMIN, TeamUser::ROLE_ACCOUNTANT],
            true,
        );
    }

    public function canManageMasterData(Team|int $team): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return in_array(
            $this->teamRole($team),
            [TeamUser::ROLE_OWNER, TeamUser::ROLE_ADMIN, TeamUser::ROLE_ACCOUNTANT, TeamUser::ROLE_MANAGER],
            true,
        );
    }

    public function canManageSales(Team|int $team): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return in_array(
            $this->teamRole($team),
            [TeamUser::ROLE_OWNER, TeamUser::ROLE_ADMIN, TeamUser::ROLE_ACCOUNTANT, TeamUser::ROLE_MANAGER],
            true,
        );
    }

    public function canManagePurchases(Team|int $team): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return in_array(
            $this->teamRole($team),
            [TeamUser::ROLE_OWNER, TeamUser::ROLE_ADMIN, TeamUser::ROLE_ACCOUNTANT, TeamUser::ROLE_MANAGER],
            true,
        );
    }

    public function canApproveTransactions(Team|int $team): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }

        return in_array(
            $this->teamRole($team),
            [TeamUser::ROLE_OWNER, TeamUser::ROLE_ADMIN, TeamUser::ROLE_ACCOUNTANT],
            true,
        );
    }
}

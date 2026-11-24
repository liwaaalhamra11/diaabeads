<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable implements FilamentUser, HasName
{
    use HasRoles, Notifiable;

    public const SUPER_ADMIN_ROLE = 'Super Admin';

    protected string $guard_name = 'admin';

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE, $this->guard_name);
    }

    /**
     * Super Admins can do everything; everyone else needs the permission
     * through one of their roles.
     */
    public function hasAdminPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->checkPermissionTo($permission, $this->guard_name);
    }
}

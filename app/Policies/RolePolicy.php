<?php

namespace App\Policies;

use App\Models\Admin;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasAdminPermission('roles.view');
    }

    public function view(Admin $user, Role $role): bool
    {
        return $user->hasAdminPermission('roles.view');
    }

    public function create(Admin $user): bool
    {
        return $user->hasAdminPermission('roles.create');
    }

    public function update(Admin $user, Role $role): bool
    {
        // The Super Admin role always has every permission; it is not editable.
        return $role->name !== Admin::SUPER_ADMIN_ROLE
            && $user->hasAdminPermission('roles.update');
    }

    public function delete(Admin $user, Role $role): bool
    {
        return $role->name !== Admin::SUPER_ADMIN_ROLE
            && $user->hasAdminPermission('roles.delete');
    }

    public function deleteAny(Admin $user): bool
    {
        return $user->hasAdminPermission('roles.delete');
    }
}

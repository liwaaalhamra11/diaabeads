<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasAdminPermission('admins.view');
    }

    public function view(Admin $user, Admin $admin): bool
    {
        return $user->hasAdminPermission('admins.view');
    }

    public function create(Admin $user): bool
    {
        return $user->hasAdminPermission('admins.create');
    }

    public function update(Admin $user, Admin $admin): bool
    {
        // Only a Super Admin may edit another Super Admin.
        if ($admin->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->hasAdminPermission('admins.update');
    }

    public function delete(Admin $user, Admin $admin): bool
    {
        if ($user->is($admin)) {
            return false; // Nobody can delete their own account.
        }

        if ($admin->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->hasAdminPermission('admins.delete');
    }

    public function deleteAny(Admin $user): bool
    {
        return $user->hasAdminPermission('admins.delete');
    }
}

<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps the standard policy abilities onto "{group}.{action}" permissions.
 */
abstract class ShopResourcePolicy
{
    /** Permission group key from App\Support\AdminPermissions. */
    abstract protected function group(): string;

    protected function allows(Admin $admin, string $action): bool
    {
        return $admin->hasAdminPermission($this->group().'.'.$action);
    }

    public function viewAny(Admin $admin): bool
    {
        return $this->allows($admin, 'view');
    }

    public function view(Admin $admin, Model $record): bool
    {
        return $this->allows($admin, 'view');
    }

    public function create(Admin $admin): bool
    {
        return $this->allows($admin, 'create');
    }

    public function update(Admin $admin, Model $record): bool
    {
        return $this->allows($admin, 'update');
    }

    /** Used by bulk actions such as "Publish selected". */
    public function updateAny(Admin $admin): bool
    {
        return $this->allows($admin, 'update');
    }

    public function delete(Admin $admin, Model $record): bool
    {
        return $this->allows($admin, 'delete');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $this->allows($admin, 'delete');
    }

    public function restore(Admin $admin, Model $record): bool
    {
        return $this->allows($admin, 'restore');
    }

    public function restoreAny(Admin $admin): bool
    {
        return $this->allows($admin, 'restore');
    }

    public function forceDelete(Admin $admin, Model $record): bool
    {
        return $this->allows($admin, 'force_delete');
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return $this->allows($admin, 'force_delete');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminPermissionSeeder::class);

        $superAdmin = Role::findOrCreate(Admin::SUPER_ADMIN_ROLE, AdminPermissions::GUARD);

        // A sensible starter role with read-only access.
        Role::findOrCreate('Viewer', AdminPermissions::GUARD)
            ->syncPermissions(['admins.view', 'roles.view']);

        $admin = Admin::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Super Admin', 'password' => 'P@ssw0rd', 'is_active' => true],
        );

        $admin->assignRole($superAdmin);
    }
}

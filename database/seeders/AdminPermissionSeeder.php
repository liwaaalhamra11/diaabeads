<?php

namespace Database\Seeders;

use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates any permissions listed in App\Support\AdminPermissions that are
 * missing from the database. Safe to run any number of times.
 */
class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermissions::all() as $name) {
            Permission::findOrCreate($name, AdminPermissions::GUARD);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

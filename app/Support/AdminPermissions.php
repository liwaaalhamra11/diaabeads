<?php

namespace App\Support;

/**
 * Single source of truth for admin panel permissions.
 *
 * Add a new group here when you add a new admin section, then run
 * `php artisan db:seed --class=AdminPermissionSeeder` to sync them.
 */
class AdminPermissions
{
    public const GUARD = 'admin';

    /**
     * group key => [label, description, actions => [action => label]]
     */
    public static function groups(): array
    {
        return [
            'categories' => [
                'label' => 'Categories',
                'description' => 'Organise products into categories.',
                'actions' => [
                    'view' => 'View categories',
                    'create' => 'Create categories',
                    'update' => 'Edit categories',
                    'delete' => 'Delete categories',
                ],
            ],
            'subcategories' => [
                'label' => 'Subcategories',
                'description' => 'Split each category into smaller groups.',
                'actions' => [
                    'view' => 'View subcategories',
                    'create' => 'Create subcategories',
                    'update' => 'Edit subcategories',
                    'delete' => 'Delete subcategories',
                ],
            ],
            'products' => [
                'label' => 'Products',
                'description' => 'Add products, prices, stock and photos.',
                'actions' => [
                    'view' => 'View products',
                    'create' => 'Create products',
                    'update' => 'Edit products',
                    'delete' => 'Move products to trash',
                    'restore' => 'Restore products from trash',
                    'force_delete' => 'Permanently delete products',
                ],
            ],
            'bills' => [
                'label' => 'Bills',
                'description' => 'Money in (sales) and money out (expenses).',
                'actions' => [
                    'view' => 'View bills',
                    'create' => 'Create bills',
                    'update' => 'Edit bills & mark as paid',
                    'delete' => 'Move bills to trash',
                    'restore' => 'Restore bills from trash',
                    'force_delete' => 'Permanently delete bills',
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'description' => 'Daily and monthly financial reports.',
                'actions' => [
                    'view' => 'View financial reports',
                    'export' => 'Export reports to Excel / PDF',
                ],
            ],
            'admins' => [
                'label' => 'Admins',
                'description' => 'Manage who can sign in to the admin panel.',
                'actions' => [
                    'view' => 'View admins',
                    'create' => 'Create admins',
                    'update' => 'Edit admins',
                    'delete' => 'Delete admins',
                ],
            ],
            'roles' => [
                'label' => 'Roles & Permissions',
                'description' => 'Manage roles and what each role is allowed to do.',
                'actions' => [
                    'view' => 'View roles',
                    'create' => 'Create roles',
                    'update' => 'Edit roles',
                    'delete' => 'Delete roles',
                ],
            ],
        ];
    }

    /** @return array<string, string> permission name => label */
    public static function optionsFor(string $group): array
    {
        $options = [];

        foreach (static::groups()[$group]['actions'] as $action => $label) {
            $options["{$group}.{$action}"] = $label;
        }

        return $options;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return collect(array_keys(static::groups()))
            ->flatMap(fn (string $group) => array_keys(static::optionsFor($group)))
            ->all();
    }
}

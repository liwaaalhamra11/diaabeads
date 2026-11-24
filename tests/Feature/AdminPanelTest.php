<?php

namespace Tests\Feature;

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->superAdmin = Admin::where('email', 'admin@admin.com')->firstOrFail();
    }

    private function viewer(): Admin
    {
        return tap(Admin::create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => 'password123']))
            ->assignRole('Viewer');
    }

    public function test_seeded_admin_can_log_in(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@admin.com', 'password' => 'P@ssw0rd'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->superAdmin, 'admin');
        $this->assertNotNull($this->superAdmin->fresh()->last_login_at);
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        Admin::create(['name' => 'Off', 'email' => 'off@example.com', 'password' => 'password123', 'is_active' => false]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'off@example.com', 'password' => 'password123'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('admin');
    }

    public function test_all_pages_render_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $role = Role::findByName('Viewer', 'admin');

        foreach (['/admin', '/admin/admins', '/admin/admins/create', "/admin/admins/{$this->superAdmin->id}/edit",
            '/admin/roles', '/admin/roles/create', "/admin/roles/{$role->id}/edit", '/admin/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_super_admin_can_create_admin_with_role(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $role = Role::findByName('Viewer', 'admin');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'Jane', 'email' => 'jane@example.com',
                'password' => 'secret123', 'password_confirmation' => 'secret123',
                'roles' => [$role->id], 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $jane = Admin::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($jane->hasRole('Viewer'));
        $this->assertTrue(password_verify('secret123', $jane->password));
    }

    public function test_role_permissions_are_saved_and_loaded(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Manager', 'permissions_admins' => ['admins.view', 'admins.create'], 'permissions_roles' => ['roles.view']])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::findByName('Manager', 'admin');
        $this->assertEqualsCanonicalizing(['admins.view', 'admins.create', 'roles.view'], $role->permissions->pluck('name')->all());

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSchemaStateSet(['permissions_admins' => ['admins.view', 'admins.create'], 'permissions_roles' => ['roles.view']])
            ->fillForm(['permissions_admins' => [], 'permissions_roles' => ['roles.view', 'roles.update']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['roles.view', 'roles.update'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_viewer_can_view_but_not_change(): void
    {
        $viewer = $this->viewer();
        $this->actingAs($viewer, 'admin');

        $this->get('/admin/admins')->assertOk();
        $this->get('/admin/roles')->assertOk();
        $this->get('/admin/admins/create')->assertForbidden();
        $this->get('/admin/roles/create')->assertForbidden();
        $this->get("/admin/admins/{$this->superAdmin->id}/edit")->assertForbidden();

        Livewire::test(ListAdmins::class)->assertActionHidden('create');
    }

    public function test_admin_without_roles_sees_only_dashboard(): void
    {
        $this->actingAs(Admin::create(['name' => 'No', 'email' => 'no@example.com', 'password' => 'password123']), 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
    }

    public function test_super_admin_role_is_protected(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $role = Role::findByName('Super Admin', 'admin');

        $this->get("/admin/roles/{$role->id}/edit")->assertForbidden();
        Livewire::test(ListRoles::class)->assertTableActionHidden('delete', $role);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(ListAdmins::class)->assertTableActionHidden('delete', $this->superAdmin);
    }

    public function test_last_super_admin_cannot_lose_role(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(EditAdmin::class, ['record' => $this->superAdmin->getRouteKey()])
            ->fillForm(['roles' => []])
            ->call('save');

        $this->assertTrue($this->superAdmin->fresh()->isSuperAdmin());
    }

    public function test_non_super_admin_cannot_grant_super_admin_role(): void
    {
        Role::findOrCreate('Manager', 'admin')->syncPermissions(['admins.view', 'admins.create', 'admins.update']);
        $manager = tap(Admin::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'password123']))->assignRole('Manager');
        $this->actingAs($manager, 'admin');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'Sneaky', 'email' => 'sneaky@example.com',
                'password' => 'secret123', 'password_confirmation' => 'secret123',
                'roles' => [Role::findByName('Super Admin', 'admin')->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $this->assertDatabaseMissing('admins', ['email' => 'sneaky@example.com']);
    }

    public function test_landing_page_and_panel_use_the_logo(): void
    {
        $this->assertFileExists(public_path('images/logo.png'));
        $this->assertFileExists(public_path('images/favicon.png'));

        $this->get('/')
            ->assertOk()
            ->assertSee('images/logo.png')
            ->assertSee('images/favicon.png');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('images/logo.png')
            ->assertSee('images/favicon.png');
    }

    public function test_landing_page_still_works(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Crafted for you')
            ->assertSee('Elegance')
            ->assertSee('Where your style matters');
    }
}

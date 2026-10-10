<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_promotes_and_verifies_an_existing_account_without_changing_its_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'admin@syberinfo.com.au', 'password' => 'my-own-password']);
        $hash = $user->password;

        $this->artisan('app:super-admin', ['email' => 'Admin@Syberinfo.com.au'])
            ->expectsOutputToContain('is a verified super admin')
            ->doesntExpectOutputToContain('Temporary password')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame($hash, $user->password);
        $this->assertSame(Role::FullAdmin, $user->roleIn($user->workspaces()->sole()));
    }

    public function test_command_creates_the_account_from_env_with_a_one_time_password(): void
    {
        config(['app.super_admin_email' => 'admin@syberinfo.com.au']);

        $this->artisan('app:super-admin', ['--company' => 'Syberinfo'])
            ->expectsOutputToContain('Temporary password (shown once)')
            ->assertSuccessful();

        $user = User::where('email', 'admin@syberinfo.com.au')->sole();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Syberinfo', $user->workspaces()->sole()->name);

        // Idempotent: a second run neither duplicates the company nor resets the password.
        $this->artisan('app:super-admin')->doesntExpectOutputToContain('Temporary password')->assertSuccessful();
        $this->assertSame(1, $user->workspaces()->count());
    }

    public function test_command_needs_an_email(): void
    {
        config(['app.super_admin_email' => null]);

        $this->artisan('app:super-admin')->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_migration_promotes_the_configured_existing_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'admin@syberinfo.com.au']);
        $other = User::factory()->unverified()->create();
        config(['app.super_admin_email' => 'admin@syberinfo.com.au']);
        $migration = require database_path('migrations/2026_10_04_000100_add_super_admin_to_users.php');

        $migration->down();
        $migration->up();

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->isSuperAdmin());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_super_admin_can_enter_any_company_as_full_admin(): void
    {
        $home = Workspace::factory()->create();
        $customer = Workspace::factory()->create(['name' => 'Customer Co']);
        $admin = $this->actingAsMember($home, Role::FullAdmin);
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->get(route('dashboard'))->assertSee('All companies (super admin)')->assertSee('Customer Co');

        $this->post(route('workspaces.switch', $customer))->assertRedirect(route('dashboard'));

        $this->assertSame(Role::FullAdmin, $admin->roleIn($customer));
    }

    public function test_regular_users_cannot_enter_other_companies_or_grant_themselves_super_admin(): void
    {
        $home = Workspace::factory()->create();
        $customer = Workspace::factory()->create(['name' => 'Customer Co']);
        $user = $this->actingAsMember($home, Role::FullAdmin);

        $this->get(route('dashboard'))->assertDontSee('Customer Co');
        $this->post(route('workspaces.switch', $customer))->assertForbidden();

        $this->patch(route('profile.update'), ['name' => 'Me', 'email' => $user->email, 'is_super_admin' => true]);
        $this->assertFalse($user->fresh()->isSuperAdmin());
    }
}

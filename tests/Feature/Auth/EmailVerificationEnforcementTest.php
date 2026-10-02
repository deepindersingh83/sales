<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_users_are_sent_to_verify_before_using_the_app(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin, ['email_verified_at' => null]);

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('leaderboard.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('admin.plans.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('profile.edit'))->assertOk(); // can still fix a mistyped email
    }

    public function test_a_pre_registered_unverified_account_cannot_be_added_as_a_member(): void
    {
        // An attacker signs up with the victim company's CFO address...
        $impostor = User::factory()->unverified()->create(['email' => 'cfo@victim.test']);

        // ...and the victim's admin later adds that address as a Full Admin.
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.members.store'), ['name' => 'CFO', 'email' => 'cfo@victim.test', 'role' => 'full_admin'])
            ->assertSessionHasErrors('email');

        $this->assertFalse($impostor->belongsToWorkspace($ws));
    }

    public function test_a_verified_existing_account_can_still_be_added(): void
    {
        $colleague = User::factory()->create(['email' => 'rep@acme.test']);
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.members.store'), ['name' => 'Rep', 'email' => 'rep@acme.test', 'role' => 'participant'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($colleague->belongsToWorkspace($ws));
    }

    public function test_csv_import_skips_unverified_existing_accounts(): void
    {
        $impostor = User::factory()->unverified()->create(['email' => 'cfo@victim.test']);
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.members.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('m.csv', "name,email,role\nCFO,cfo@victim.test,full_admin\n"),
        ])->assertSessionHas('import_errors');

        $this->assertFalse($impostor->belongsToWorkspace($ws));
    }

    public function test_new_members_created_by_an_admin_are_sent_a_verification_email(): void
    {
        Notification::fake();
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.members.store'), ['name' => 'New', 'email' => 'new@acme.test', 'role' => 'participant']);

        Notification::assertSentTo(User::where('email', 'new@acme.test')->sole(), VerifyEmail::class);
    }

    public function test_completing_a_password_reset_verifies_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post(route('password.store'), [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_operators_can_verify_accounts_from_the_console(): void
    {
        $one = User::factory()->unverified()->create(['email' => 'one@acme.test']);
        $two = User::factory()->unverified()->create();

        $this->artisan('users:verify', ['email' => 'one@acme.test'])->assertSuccessful();
        $this->assertTrue($one->fresh()->hasVerifiedEmail());
        $this->assertFalse($two->fresh()->hasVerifiedEmail());

        $this->artisan('users:verify', ['--all' => true])->expectsConfirmation(
            'Mark 1 unverified account(s) as verified? Only do this for accounts you trust.', 'yes'
        )->assertSuccessful();
        $this->assertTrue($two->fresh()->hasVerifiedEmail());

        $this->artisan('users:verify')->assertFailed();
    }
}

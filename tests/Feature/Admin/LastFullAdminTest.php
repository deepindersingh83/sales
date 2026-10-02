<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LastFullAdminTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('members.csv', $contents);
    }

    public function test_member_import_without_a_role_column_keeps_existing_roles(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $admin = $this->actingAsMember($ws, Role::FullAdmin, ['email' => 'boss@acme.test']);

        $this->post(route('admin.members.import.store'), [
            'file' => $this->csv("name,email\nBoss,boss@acme.test\nNew Rep,rep@acme.test\n"),
        ])->assertRedirect(route('admin.members.index'));

        $this->assertSame(Role::FullAdmin, $admin->roleIn($ws));
        $this->assertSame(Role::Participant, User::where('email', 'rep@acme.test')->sole()->roleIn($ws));
    }

    public function test_member_import_cannot_demote_the_only_full_admin(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $admin = $this->actingAsMember($ws, Role::FullAdmin, ['email' => 'boss@acme.test']);

        $this->post(route('admin.members.import.store'), [
            'file' => $this->csv("name,email,role\nBoss,boss@acme.test,participant\n"),
        ])->assertSessionHas('import_errors');

        $this->assertSame(Role::FullAdmin, $admin->roleIn($ws));
    }

    public function test_member_import_respects_the_free_tier_member_cap(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'free']);
        $this->actingAsMember($ws, Role::FullAdmin);
        $limit = $ws->payeeLimit();
        $rows = collect(range(1, $limit + 2))->map(fn ($i) => "Rep {$i},rep{$i}@acme.test")->implode("\n");

        $this->post(route('admin.members.import.store'), ['file' => $this->csv("name,email\n{$rows}\n")])
            ->assertSessionHas('import_errors');

        $this->assertSame($limit, $ws->users()->count());
    }

    public function test_sole_full_admin_cannot_delete_their_account_while_others_remain(): void
    {
        $ws = Workspace::factory()->create();
        $admin = $this->actingAsMember($ws, Role::FullAdmin);
        $this->makeMember($ws, Role::Participant);

        $this->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($admin->fresh());
    }

    public function test_a_full_admin_with_a_co_admin_can_delete_their_account(): void
    {
        $ws = Workspace::factory()->create();
        $admin = $this->actingAsMember($ws, Role::FullAdmin);
        $this->makeMember($ws, Role::FullAdmin);

        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $this->assertNull($admin->fresh());
    }
}

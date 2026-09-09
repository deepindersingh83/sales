<?php

namespace Tests\Feature\Tenancy;

use App\Actions\ProvisionWorkspace;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_workspace_owned_as_full_admin(): void
    {
        $this->post('/register', [
            'name' => 'Dana Owner',
            'workspace_name' => 'Acme Sales',
            'email' => 'dana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'dana@example.com')->firstOrFail();
        $workspace = Workspace::where('name', 'Acme Sales')->firstOrFail();

        $this->assertTrue($user->belongsToWorkspace($workspace));
        $this->assertSame(Role::FullAdmin, $user->roleIn($workspace));
        $this->assertNotEmpty($workspace->slug);
    }

    public function test_a_user_only_sees_their_own_workspace_memberships(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $provision = app(ProvisionWorkspace::class);
        $aliceWs = $provision->handle($alice, 'Alice Co');
        $bobWs = $provision->handle($bob, 'Bob Co');

        $this->assertEqualsCanonicalizing(
            [$aliceWs->id],
            $alice->workspaces()->pluck('workspaces.id')->all()
        );
        $this->assertFalse($alice->belongsToWorkspace($bobWs));
        $this->assertNull($alice->roleIn($bobWs));
    }

    public function test_context_can_run_a_callback_as_a_specific_workspace(): void
    {
        $context = app(WorkspaceContext::class);
        $ws = Workspace::factory()->create();

        $this->assertFalse($context->has());

        $seen = $context->runAs($ws, fn () => $context->id());

        $this->assertSame($ws->id, $seen);
        // Context is restored afterwards.
        $this->assertFalse($context->has());
    }
}

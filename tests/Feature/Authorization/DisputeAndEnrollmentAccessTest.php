<?php

namespace Tests\Feature\Authorization;

use App\Enums\DisputeStatus;
use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Dispute;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeAndEnrollmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dispute_cannot_reference_a_transaction_the_rep_was_not_credited_for(): void
    {
        $other = Workspace::factory()->create();
        $this->useWorkspace($other);
        $foreign = Transaction::create(['workspace_id' => $other->id, 'external_id' => 'X-1', 'source_system' => 'csv',
            'amount' => 1, 'currency' => 'USD', 'raw_data' => []]);

        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);

        $this->post(route('disputes.store'), [
            'category' => 'Missing credit', 'description' => 'Where is it?', 'transaction_id' => $foreign->id,
        ])->assertSessionHasErrors('transaction_id');

        $this->assertSame(0, Dispute::count());
    }

    public function test_claim_response_does_not_reveal_whether_a_deal_exists(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'REAL-1', 'source_system' => 'csv',
            'amount' => 1, 'currency' => 'USD', 'raw_data' => []]);

        $real = $this->post(route('disputes.claim'), ['external_id' => 'REAL-1'])->getSession()->get('status');
        $fake = $this->post(route('disputes.claim'), ['external_id' => 'NOPE-9'])->getSession()->get('status');

        $this->assertSame($real, $fake);
    }

    public function test_an_admin_cannot_resolve_their_own_dispute(): void
    {
        $ws = Workspace::factory()->create();
        $admin = $this->actingAsMember($ws, Role::FullAdmin);
        $dispute = Dispute::create(['workspace_id' => $ws->id, 'user_id' => $admin->id, 'category' => 'Pay',
            'description' => 'Give me more', 'status' => DisputeStatus::Open]);

        $this->patch(route('disputes.resolve', $dispute))->assertForbidden();
    }

    public function test_a_resolved_dispute_cannot_be_resolved_again(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->makeMember($ws, Role::Participant);
        $this->actingAsMember($ws, Role::FullAdmin);
        $dispute = Dispute::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'category' => 'Pay',
            'description' => 'x', 'status' => DisputeStatus::Resolved, 'resolution_notes' => 'Paid in October']);

        $this->patch(route('disputes.resolve', $dispute), ['resolution_notes' => 'Overwritten'])->assertForbidden();

        $this->assertSame('Paid in October', $dispute->fresh()->resolution_notes);
    }

    public function test_enrollment_and_dashboard_hide_plans_hidden_from_a_limited_admin(): void
    {
        $ws = Workspace::factory()->create();
        $limited = $this->actingAsMember($ws, Role::LimitedAdmin);
        $hidden = Plan::factory()->for($ws)->create(['name' => 'Secret Plan', 'status' => PlanStatus::Active]);
        $hidden->hiddenFromUsers()->attach($limited->id);

        CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $hidden->id, 'status' => 'completed']);

        $this->get(route('enrollments.index'))->assertOk()->assertDontSee('Secret Plan');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Secret Plan');
        $this->post(route('enrollments.sign', $hidden), ['signature' => 'Me'])->assertNotFound();
    }

    public function test_participants_can_still_self_enroll(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);
        $plan = Plan::factory()->for($ws)->create(['name' => 'Open Plan', 'status' => PlanStatus::Active]);

        $this->get(route('enrollments.index'))->assertOk()->assertSee('Open Plan');
        $this->post(route('enrollments.sign', $plan), ['signature' => 'Rep'])->assertRedirect(route('enrollments.index'));
    }
}

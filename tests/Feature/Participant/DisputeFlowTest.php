<?php

namespace Tests\Feature\Participant;

use App\Enums\DisputeStatus;
use App\Enums\Role;
use App\Models\Dispute;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_raise_and_view_their_dispute(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->actingAsMember($ws, Role::Participant);

        $this->post(route('disputes.store'), [
            'category' => 'Missing credit',
            'description' => 'A deal I closed is not showing.',
        ])->assertRedirect();

        $dispute = Dispute::firstOrFail();
        $this->assertSame($rep->id, $dispute->user_id);
        $this->assertSame(DisputeStatus::Open, $dispute->status);
        $this->assertSame($ws->id, $dispute->workspace_id);

        $this->get(route('disputes.show', $dispute))->assertOk()->assertSee('Missing credit');
    }

    public function test_participant_cannot_view_another_reps_dispute(): void
    {
        $ws = Workspace::factory()->create();
        $other = $this->makeMember($ws, Role::Participant);
        $dispute = Dispute::create([
            'workspace_id' => $ws->id, 'user_id' => $other->id,
            'category' => 'Other', 'description' => 'private', 'status' => DisputeStatus::Open,
        ]);

        $this->actingAsMember($ws, Role::Participant);
        $this->get(route('disputes.show', $dispute))->assertForbidden();
    }

    public function test_admin_can_triage_comment_and_resolve(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->makeMember($ws, Role::Participant);
        $dispute = Dispute::create([
            'workspace_id' => $ws->id, 'user_id' => $rep->id,
            'category' => 'Incorrect amount', 'description' => 'Off by $50', 'status' => DisputeStatus::Open,
        ]);

        $this->actingAsMember($ws, Role::FullAdmin);

        // Queue lists it.
        $this->get(route('admin.disputes.index'))->assertOk()->assertSee('Incorrect amount');

        // Commenting moves Open -> Investigating.
        $this->post(route('disputes.comments.store', $dispute), ['comment' => 'Looking into it.'])->assertRedirect();
        $this->assertSame(DisputeStatus::Investigating, $dispute->refresh()->status);

        // Resolve.
        $this->patch(route('disputes.resolve', $dispute), ['resolution_notes' => 'Corrected in next run.'])->assertRedirect();
        $dispute->refresh();
        $this->assertSame(DisputeStatus::Resolved, $dispute->status);
        $this->assertSame('Corrected in next run.', $dispute->resolution_notes);
    }

    public function test_participant_cannot_resolve_their_own_dispute(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->actingAsMember($ws, Role::Participant);
        $dispute = Dispute::create([
            'workspace_id' => $ws->id, 'user_id' => $rep->id,
            'category' => 'Other', 'description' => 'x', 'status' => DisputeStatus::Open,
        ]);

        $this->patch(route('disputes.resolve', $dispute), [])->assertForbidden();
        $this->assertSame(DisputeStatus::Open, $dispute->refresh()->status);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_create_and_publish_an_announcement(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.announcements.store'), [
            'title' => 'Q3 kickoff', 'body' => 'New plan is live.', 'publish' => '1',
        ])->assertRedirect();

        $a = Announcement::firstOrFail();
        $this->assertSame($ws->id, $a->workspace_id);
        $this->assertNotNull($a->published_at);
    }

    public function test_limited_admin_cannot_create_announcements(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);

        $this->get(route('admin.announcements.create'))->assertForbidden();
        $this->post(route('admin.announcements.store'), ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_participant_sees_only_published_announcements_on_dashboard(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);

        // Create via factory/model in this workspace context.
        Announcement::create(['title' => 'Live one', 'body' => 'visible', 'audience' => 'all', 'published_at' => now()]);
        Announcement::create(['title' => 'Draft one', 'body' => 'hidden', 'audience' => 'all', 'published_at' => null]);

        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertSee('Live one')->assertDontSee('Draft one');
    }
}

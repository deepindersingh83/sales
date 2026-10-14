<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Alias;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AliasManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_blank_split_percent_credits_the_whole_deal(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $rep = $this->makeMember($ws, Role::Participant);

        $this->post(route('admin.aliases.store'), [
            'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep',
            'match_type' => 'exact', 'split_percent' => '',
        ])->assertRedirect(route('admin.aliases.index'));

        $this->assertEqualsWithDelta(100.0, (float) Alias::sole()->split_percent, 0.001);
    }
}

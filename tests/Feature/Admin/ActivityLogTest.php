<?php

namespace Tests\Feature\Admin;

use App\Enums\CalcRunStatus;
use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\CalcRun;
use App\Models\ImportSource;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /** Every stored activity column, flattened, for "never contains" checks. */
    private function storedActivity(): string
    {
        return DB::table('activity_logs')->get()->toJson();
    }

    public function test_updating_a_plan_records_who_changed_which_fields(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->actingAsMember($workspace, Role::FullAdmin);
        $plan = Plan::factory()->for($workspace)->create(['name' => 'Old name', 'currency' => 'USD']);

        $this->put(route('admin.plans.update', $plan), [
            'name' => 'New name',
            'period_type' => $plan->period_type,
            'status' => PlanStatus::Draft->value,
            'performance_metric' => 'revenue',
            'currency' => 'USD',
            'start_date' => $plan->start_date->toDateString(),
            'end_date' => $plan->end_date->toDateString(),
            'description' => $plan->description,
            'tiers' => [['threshold_from' => 0, 'threshold_to' => '', 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => 1]],
        ])->assertRedirect();

        $log = ActivityLog::where('action', 'plan.updated')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertTrue($log->subject->is($plan));
        $this->assertSame(['old' => 'Old name', 'new' => 'New name'], $log->properties['changes']['name']);
        $this->assertArrayHasKey('tiers', $log->properties['changes']);
        $this->assertArrayNotHasKey('currency', $log->properties['changes']);
    }

    public function test_approving_a_run_and_adding_an_adjustment_are_recorded(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->actingAsMember($workspace, Role::FullAdmin);
        $rep = $this->makeMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create();
        $run = CalcRun::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'status' => CalcRunStatus::Completed]);

        $this->post(route('admin.calc-runs.approve', $run))->assertRedirect();
        $this->post(route('admin.calc-runs.adjustments.store', $run), ['user_id' => $rep->id, 'amount' => -50, 'reason' => 'Clawback for refund'])->assertRedirect();

        $approved = ActivityLog::where('action', 'calc_run.approved')->sole();
        $this->assertSame($admin->id, $approved->user_id);
        $this->assertTrue($approved->subject->is($run));

        $adjustment = ActivityLog::where('action', 'adjustment.added')->sole();
        $this->assertSame($rep->id, $adjustment->properties['user_id']);
        $this->assertEquals(-50, $adjustment->properties['amount']);
        $this->assertSame('Clawback for refund', $adjustment->properties['reason']);
    }

    public function test_releasing_rewards_is_recorded_only_when_something_changed(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAsMember($workspace, Role::FullAdmin);
        $rep = $this->makeMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create();
        $run = CalcRun::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'status' => CalcRunStatus::Completed, 'approved_at' => now()]);
        $run->rewards()->create(['workspace_id' => $workspace->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 10, 'currency' => 'USD', 'status' => 'reviewed']);

        $this->post(route('admin.calc-runs.rewards.transition', $run), ['action' => 'release'])->assertRedirect();
        $this->post(route('admin.calc-runs.rewards.transition', $run), ['action' => 'release'])->assertRedirect();

        $log = ActivityLog::where('action', 'rewards.released')->sole();
        $this->assertSame(1, $log->properties['count']);
    }

    public function test_changing_a_members_role_records_it_without_salary_figures(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAsMember($workspace, Role::FullAdmin);
        $member = $this->makeMember($workspace, Role::Participant);

        $this->put(route('admin.members.update', $member), ['role' => Role::PlanAdmin->value, 'salary' => 98765])->assertRedirect();

        $log = ActivityLog::where('action', 'member.role_changed')->sole();
        $this->assertSame(['old' => 'participant', 'new' => 'plan_admin'], $log->properties['changes']['role']);
        $this->assertSame(['changed' => true], $log->properties['changes']['salary']);
        $this->assertStringNotContainsString('98765', $this->storedActivity());
    }

    public function test_saving_xero_app_keys_never_logs_the_client_secret(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->actingAsMember($workspace, Role::FullAdmin);

        $this->put(route('admin.connectors.xero.app.update'), ['client_id' => 'ws-client', 'client_secret' => 'super-secret-value'])
            ->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'xero.app_keys_saved')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringNotContainsString('super-secret-value', $this->storedActivity());
    }

    public function test_a_rejected_xero_change_is_not_logged(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAsMember($workspace, Role::FullAdmin);
        $source = ImportSource::create(['workspace_id' => $workspace->id, 'name' => 'Xero — Demo', 'type' => 'xero', 'schedule' => 'daily', 'config' => ['tenant_id' => 'T-1']]);

        $this->put(route('admin.connectors.xero.update', $source), ['schedule' => 'yearly'])->assertSessionHasErrors('schedule');
        $this->put(route('admin.connectors.xero.update', $source), ['schedule' => 'hourly'])->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'xero.schedule_changed')->sole();
        $this->assertTrue($log->subject->is($source));
        $this->assertSame(['schedule' => 'hourly'], $log->properties);
    }

    public function test_disconnecting_xero_is_recorded(): void
    {
        Http::fake(['*' => Http::response(null, 204)]);
        $workspace = Workspace::factory()->create();
        $this->actingAsMember($workspace, Role::FullAdmin);
        $source = ImportSource::create(['workspace_id' => $workspace->id, 'name' => 'Xero — Demo', 'type' => 'xero', 'schedule' => 'daily',
            'config' => ['tenant_id' => 'T-1', 'connection_id' => 'C-1'], 'credentials' => ['access_token' => 'access-abc', 'refresh_token' => 'refresh-abc']]);

        $this->delete(route('admin.connectors.xero.destroy', $source))->assertRedirect();

        $this->assertSame(1, ActivityLog::where('action', 'xero.disconnected')->where('subject_id', $source->id)->count());
        $this->assertStringNotContainsString('access-abc', $this->storedActivity());
    }

    public function test_regenerating_the_api_token_does_not_log_the_token(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAsMember($workspace, Role::FullAdmin);

        $response = $this->post(route('admin.settings.api-token'));

        $token = $response->getSession()->get('api_token');
        $this->assertNotEmpty($token);
        $this->assertSame(1, ActivityLog::where('action', 'workspace.api_token_regenerated')->count());
        $this->assertStringNotContainsString($token, $this->storedActivity());
        $this->assertStringNotContainsString(Workspace::hashApiToken($token), $this->storedActivity());
    }

    public function test_logger_works_without_a_signed_in_user_and_drops_credential_keys(): void
    {
        $workspace = Workspace::factory()->create();
        $this->useWorkspace($workspace);

        $log = app(ActivityLogger::class)->log('xero.synced', 'Console sync.', null, [
            'rows' => 3,
            'client_secret' => 'abc',
            'nested' => ['access_token' => 'xyz', 'kept' => true],
        ]);

        $this->assertNull($log->user_id);
        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame(['rows' => 3, 'nested' => ['kept' => true]], $log->fresh()->properties);
    }

    public function test_activity_page_shows_only_the_current_workspaces_entries(): void
    {
        $other = Workspace::factory()->create();
        ActivityLog::factory()->for($other)->create(['description' => 'Other tenant secret plan']);
        $workspace = Workspace::factory()->create();
        ActivityLog::factory()->for($workspace)->create(['description' => 'Our own change']);
        $this->actingAsMember($workspace, Role::FullAdmin);

        $this->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Our own change')
            ->assertDontSee('Other tenant secret plan');
    }

    public function test_activity_page_filters_by_user_and_action_prefix(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->actingAsMember($workspace, Role::FullAdmin);
        ActivityLog::factory()->for($workspace)->create(['user_id' => $admin->id, 'action' => 'plan.updated', 'description' => 'Edited a plan']);
        ActivityLog::factory()->for($workspace)->create(['user_id' => $admin->id, 'action' => 'alias.created', 'description' => 'Made an alias']);
        ActivityLog::factory()->for($workspace)->create(['user_id' => null, 'action' => 'plan.deleted', 'description' => 'System removed a plan']);

        $this->get(route('admin.activity.index', ['action' => 'plan.', 'user_id' => $admin->id]))
            ->assertOk()
            ->assertSee('Edited a plan')
            ->assertDontSee('Made an alias')
            ->assertDontSee('System removed a plan');
    }

    public function test_activity_can_be_exported_as_csv(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAsMember($workspace, Role::FullAdmin);
        ActivityLog::factory()->for($workspace)->create(['action' => 'alias.created', 'description' => '=HYPERLINK("x")']);

        $response = $this->get(route('admin.activity.index', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('alias.created', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_limited_admin_does_not_see_entries_about_hidden_plans(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = $this->actingAsMember($workspace, Role::LimitedAdmin);
        $hidden = Plan::factory()->for($workspace)->create();
        $hidden->hiddenFromUsers()->attach($admin->id);
        $hiddenRun = CalcRun::create(['workspace_id' => $workspace->id, 'plan_id' => $hidden->id, 'status' => CalcRunStatus::Completed]);
        $visible = Plan::factory()->for($workspace)->create();
        ActivityLog::factory()->for($workspace)->create(['subject_type' => $hidden->getMorphClass(), 'subject_id' => $hidden->id, 'description' => 'Hidden plan edited']);
        ActivityLog::factory()->for($workspace)->create(['subject_type' => $hiddenRun->getMorphClass(), 'subject_id' => $hiddenRun->id, 'description' => 'Hidden run approved']);
        ActivityLog::factory()->for($workspace)->create(['subject_type' => $visible->getMorphClass(), 'subject_id' => $visible->id, 'description' => 'Visible plan edited']);

        $this->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Visible plan edited')
            ->assertDontSee('Hidden plan edited')
            ->assertDontSee('Hidden run approved');
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesWithoutAccess(): array
    {
        return [
            'participant' => [Role::Participant],
            'plan admin' => [Role::PlanAdmin],
        ];
    }

    #[DataProvider('rolesWithoutAccess')]
    public function test_roles_without_access_cannot_open_the_activity_page(Role $role): void
    {
        $this->actingAsMember(Workspace::factory()->create(), $role);

        $this->get(route('admin.activity.index'))->assertForbidden();
    }
}

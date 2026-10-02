<?php

namespace Tests\Feature\Api;

use App\Enums\PayoutStatus;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Reporting\ODataFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ODataTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithToken(): array
    {
        $ws = Workspace::factory()->create();

        return [$ws, $ws->regenerateApiToken()];
    }

    private function transaction(Workspace $ws, string $id, float $amount, string $date): Transaction
    {
        return Transaction::factory()->for($ws)->create([
            'external_id' => $id, 'source_system' => 'xero', 'amount' => $amount,
            'currency' => 'AUD', 'transaction_date' => $date, 'raw_data' => ['customer' => "Customer {$id}"],
        ]);
    }

    public function test_feed_requires_a_token_and_challenges_for_basic_auth(): void
    {
        $this->getJson('/api/v1/odata/Transactions')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate');
    }

    public function test_basic_auth_with_token_as_password_is_accepted(): void
    {
        [, $token] = $this->workspaceWithToken();

        $this->withHeader('Authorization', 'Basic '.base64_encode("powerbi:{$token}"))
            ->getJson('/api/v1/odata')
            ->assertOk()
            ->assertHeader('OData-Version', '4.0')
            ->assertJsonPath('value.0.name', 'Payouts');
    }

    public function test_basic_auth_with_a_wrong_password_is_rejected(): void
    {
        $this->workspaceWithToken();

        $this->withHeader('Authorization', 'Basic '.base64_encode('powerbi:wsk_wrong'))
            ->getJson('/api/v1/odata/Transactions')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate');
    }

    public function test_metadata_describes_every_entity_set(): void
    {
        [, $token] = $this->workspaceWithToken();

        $xml = $this->withToken($token)->get('/api/v1/odata/$metadata')->assertOk()->getContent();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        foreach (['Payouts', 'Credits', 'Transactions'] as $set) {
            $this->assertStringContainsString("EntitySet Name=\"{$set}\"", $xml);
        }
        $this->assertStringContainsString('<Property Name="TransactionDate" Type="Edm.Date"/>', $xml);
    }

    public function test_entity_set_supports_filter_orderby_select_and_count(): void
    {
        [$ws, $token] = $this->workspaceWithToken();
        $this->transaction($ws, 'A', 100, '2026-07-01');
        $this->transaction($ws, 'B', 300, '2026-08-01');
        $this->transaction($ws, 'C', 200, '2026-08-15');

        $this->withToken($token)
            ->getJson('/api/v1/odata/Transactions?'.http_build_query([
                '$filter' => "TransactionDate ge 2026-08-01 and SourceSystem eq 'xero'",
                '$orderby' => 'Amount desc',
                '$select' => 'ExternalId,Amount,Customer',
                '$count' => 'true',
            ]))
            ->assertOk()
            ->assertExactJson([
                '@odata.context' => url('/api/v1/odata/$metadata#Transactions'),
                '@odata.count' => 2,
                'value' => [
                    ['ExternalId' => 'B', 'Amount' => 300, 'Customer' => 'Customer B'],
                    ['ExternalId' => 'C', 'Amount' => 200, 'Customer' => 'Customer C'],
                ],
            ]);
    }

    public function test_string_literals_with_quotes_are_bound_not_injected(): void
    {
        [$ws, $token] = $this->workspaceWithToken();
        $this->transaction($ws, "O'Brien", 100, '2026-07-01');
        $this->transaction($ws, 'X', 100, '2026-07-01');

        $this->withToken($token)
            ->getJson('/api/v1/odata/Transactions?'.http_build_query(['$filter' => "ExternalId eq 'O''Brien'"]))
            ->assertOk()
            ->assertJsonCount(1, 'value')
            ->assertJsonPath('value.0.ExternalId', "O'Brien");
    }

    public function test_top_limits_results_without_a_next_link(): void
    {
        [$ws, $token] = $this->workspaceWithToken();
        foreach (range(1, 3) as $i) {
            $this->transaction($ws, "T{$i}", $i, '2026-07-01');
        }

        $page = $this->withToken($token)->getJson('/api/v1/odata/Transactions?$top=2')->assertOk();

        // $top is honoured and, once reached, no further page is offered.
        $page->assertJsonCount(2, 'value');
        $this->assertArrayNotHasKey('@odata.nextLink', $page->json());
    }

    public function test_next_link_is_emitted_when_rows_exceed_the_page_size(): void
    {
        [$ws, $token] = $this->workspaceWithToken();
        $rows = [];
        foreach (range(1, ODataFeed::PAGE_SIZE + 1) as $i) {
            $rows[] = ['workspace_id' => $ws->id, 'external_id' => "T{$i}", 'source_system' => 'csv', 'amount' => 1,
                'currency' => 'USD', 'raw_data' => '[]', 'created_at' => now(), 'updated_at' => now()];
        }
        Transaction::insert($rows);

        $first = $this->withToken($token)->getJson('/api/v1/odata/Transactions?$select=Id')->assertOk();

        $first->assertJsonCount(ODataFeed::PAGE_SIZE, 'value');
        $next = $first->json()['@odata.nextLink'];
        $this->assertStringContainsString('%24skip='.ODataFeed::PAGE_SIZE, $next);

        $this->withToken($token)->getJson($next)->assertOk()->assertJsonCount(1, 'value');
    }

    public function test_payouts_are_released_only_and_tenant_scoped(): void
    {
        [$ws, $token] = $this->workspaceWithToken();
        $this->useWorkspace($ws);
        $rep = User::factory()->create(['name' => 'Alice']);
        $plan = Plan::factory()->for($ws)->create(['name' => 'AE Plan']);
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        foreach ([PayoutStatus::Released, PayoutStatus::Pending] as $status) {
            Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id,
                'reward_type' => 'commission', 'computed_amount' => 250, 'currency' => 'USD', 'status' => $status]);
        }
        $other = Workspace::factory()->create();
        $this->transaction($other, 'OTHER', 1, '2026-07-01');

        $this->withToken($token)->getJson('/api/v1/odata/Payouts')
            ->assertOk()
            ->assertJsonCount(1, 'value')
            ->assertJsonPath('value.0.UserName', 'Alice')
            ->assertJsonPath('value.0.PlanName', 'AE Plan');

        $this->withToken($token)->getJson('/api/v1/odata/Transactions')->assertOk()->assertJsonCount(0, 'value');
    }

    public function test_unsupported_query_options_return_an_odata_error(): void
    {
        [, $token] = $this->workspaceWithToken();

        foreach ([
            ['$filter' => 'Amount gt 1 or Amount lt 0'],
            ['$filter' => 'Customer eq \'Acme\''],   // computed property: not filterable
            ['$filter' => 'Nope eq 1'],
            ['$orderby' => 'Amount; drop table transactions'],
            ['$select' => 'Secret'],
            ['$top' => '-1'],
            ['$filter' => 'Amount gt 1 and '],                     // dangling "and"
            ['$filter' => 'TransactionDate ge 2026-99-99'],        // impossible date
            ['$filter' => 'TransactionDate ge 2026-02-30'],
            ['$expand' => 'Credits'],                              // unsupported option
            ['$search' => 'acme'],
            ['$format' => 'xml'],
        ] as $options) {
            $this->withToken($token)
                ->getJson('/api/v1/odata/Transactions?'.http_build_query($options))
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'BadRequest');
        }
    }

    public function test_unknown_entity_sets_are_not_routed(): void
    {
        [, $token] = $this->workspaceWithToken();

        $this->withToken($token)->getJson('/api/v1/odata/Users')->assertNotFound();
    }
}

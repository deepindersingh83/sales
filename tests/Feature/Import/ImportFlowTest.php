<?php

namespace Tests\Feature\Import;

use App\Enums\Role;
use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $csv = "Deal ID,Sales Amount,Currency,Close Date,Rep\nD-1,1000,USD,2026-02-01,Alice\nD-2,2000,USD,2026-02-02,Bob\n";

    public function test_admin_can_upload_preview_and_import(): void
    {
        Storage::fake('local');
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        // Step 1-2: upload -> preview with auto-detected mapping.
        $preview = $this->post(route('admin.imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('deals.csv', $this->csv),
        ]);
        $preview->assertOk();
        $token = $preview->viewData('token');
        $this->assertNotEmpty($token);
        $this->assertSame('Deal ID', $preview->viewData('mapping')['external_id']);

        // Step 3: confirm mapping -> import.
        $this->post(route('admin.imports.store'), [
            'token' => $token,
            'source_name' => 'CRM export',
            'mapping' => [
                'external_id' => 'Deal ID',
                'amount' => 'Sales Amount',
                'currency' => 'Currency',
                'transaction_date' => 'Close Date',
            ],
        ])->assertRedirect(route('admin.transactions.index'));

        $this->assertSame(2, Transaction::count());
        $this->assertNotNull(Transaction::where('external_id', 'D-1')->first());
    }

    public function test_participant_cannot_import(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);

        $this->get(route('admin.imports.create'))->assertForbidden();
    }

    public function test_limited_admin_cannot_import_but_can_view_transactions(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);

        $this->get(route('admin.transactions.index'))->assertOk();
        $this->get(route('admin.imports.create'))->assertForbidden();
    }
}

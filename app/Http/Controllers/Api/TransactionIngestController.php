<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Import\TransactionUpserter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST ingest endpoint (also the webhook target). Accepts a batch of
 * transactions and upserts them idempotently, exactly like the CSV importer.
 */
class TransactionIngestController extends Controller
{
    public function store(Request $request, TransactionUpserter $upserter): JsonResponse
    {
        $data = $request->validate([
            'source_system' => ['nullable', 'string', 'max:100'],
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.external_id' => ['required', 'string'],
            'transactions.*.amount' => ['required', 'numeric'],
            'transactions.*.profit_amount' => ['nullable', 'numeric'],
            'transactions.*.currency' => ['nullable', 'string', 'size:3'],
            'transactions.*.transaction_date' => ['nullable', 'date'],
            'transactions.*.raw_data' => ['nullable', 'array'],
        ]);

        $sourceSystem = $data['source_system'] ?? 'api';

        $rows = array_map(fn (array $t) => [
            'external_id' => $t['external_id'],
            'amount' => $t['amount'],
            'profit_amount' => $t['profit_amount'] ?? null,
            'currency' => $t['currency'] ?? 'USD',
            'transaction_date' => $t['transaction_date'] ?? null,
            'raw_data' => $t['raw_data'] ?? $t,
        ], $data['transactions']);

        $result = $upserter->upsert($rows, $sourceSystem);

        return response()->json(['result' => $result], 201);
    }
}

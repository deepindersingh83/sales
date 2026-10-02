<?php

namespace App\Http\Controllers;

use App\Enums\DisputeStatus;
use App\Models\Credit;
use App\Models\Dispute;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisputeController extends Controller
{
    /** A participant's own disputes. */
    public function index(Request $request): View
    {
        $disputes = Dispute::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('disputes.index', ['disputes' => $disputes]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Dispute::class);

        $transactions = Transaction::whereIn('id', $this->creditedTransactionIds($request))->get();

        return view('disputes.create', ['transactions' => $transactions]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Dispute::class);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            // Only a transaction the rep was credited for (as offered on the form).
            'transaction_id' => ['nullable', 'integer', Rule::in($this->creditedTransactionIds($request))],
        ]);

        $dispute = Dispute::create([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'status' => DisputeStatus::Open,
        ]);

        return redirect()->route('disputes.show', $dispute)->with('status', 'Dispute submitted.');
    }

    /** Claim a transaction the rep believes should be credited to them. */
    public function claim(Request $request): RedirectResponse
    {
        Gate::authorize('create', Dispute::class);

        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $transaction = Transaction::where('external_id', $data['external_id'])->first();

        $dispute = Dispute::create([
            'user_id' => $request->user()->id,
            'category' => 'Transaction claim',
            'description' => 'Claiming transaction '.$data['external_id'].'. '.($data['note'] ?? ''),
            'transaction_id' => $transaction?->id,
            'status' => DisputeStatus::Open,
        ]);

        // Same answer whether or not the id exists, so claims can't be used to
        // probe which deals are in the workspace.
        return redirect()->route('disputes.show', $dispute)->with('status', 'Claim submitted for review.');
    }

    /**
     * Transactions the rep may reference: those they hold a released credit for.
     *
     * @return array<int, int>
     */
    protected function creditedTransactionIds(Request $request): array
    {
        return Credit::released()
            ->where('user_id', $request->user()->id)
            ->pluck('transaction_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function show(Dispute $dispute): View
    {
        Gate::authorize('view', $dispute);

        $dispute->load(['comments.user', 'user', 'transaction', 'plan']);

        return view('disputes.show', ['dispute' => $dispute]);
    }

    public function storeComment(Request $request, Dispute $dispute): RedirectResponse
    {
        Gate::authorize('comment', $dispute);

        $data = $request->validate(['comment' => ['required', 'string']]);

        $dispute->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $data['comment'],
        ]);

        // Any comment on an open dispute moves it into investigating.
        if ($dispute->status === DisputeStatus::Open) {
            $dispute->update(['status' => DisputeStatus::Investigating]);
        }

        return redirect()->route('disputes.show', $dispute)->with('status', 'Comment added.');
    }

    public function resolve(Request $request, Dispute $dispute): RedirectResponse
    {
        Gate::authorize('resolve', $dispute);

        $data = $request->validate(['resolution_notes' => ['nullable', 'string']]);

        $dispute->update([
            'status' => DisputeStatus::Resolved,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return redirect()->route('disputes.show', $dispute)->with('status', 'Dispute resolved.');
    }
}

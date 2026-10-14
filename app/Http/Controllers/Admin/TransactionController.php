<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /**
     * Transactions with search (invoice number, external id, customer,
     * reference) and customer / payment-status filters. Filtering to one
     * customer also shows their revenue, paid and outstanding totals.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Transaction::class);

        $filters = [
            'q' => is_string($request->query('q')) ? trim($request->query('q')) : '',
            'customer' => is_string($request->query('customer')) ? $request->query('customer') : '',
            'status' => in_array($request->query('status'), ['paid', 'unpaid'], true) ? $request->query('status') : '',
        ];

        $query = Transaction::query()
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w->where('external_id', 'like', $like)
                    ->orWhere('raw_data->invoice_number', 'like', $like)
                    ->orWhere('raw_data->customer', 'like', $like)
                    ->orWhere('raw_data->reference', 'like', $like));
            })
            ->when($filters['customer'] !== '', fn ($q) => $q->where('raw_data->customer', $filters['customer']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('is_paid', $filters['status'] === 'paid'));

        $summary = null;
        if ($filters['customer'] !== '') {
            $included = (clone $query)->where('excluded', false)->get();
            $summary = [
                'invoices' => $included->count(),
                'revenue' => $included->sum(fn (Transaction $t) => (float) $t->amount),
                'outstanding' => $included->sum(fn (Transaction $t) => (float) $t->amount * $t->outstandingFraction()),
                'currencies' => $included->pluck('currency')->unique()->values(),
            ];
        }

        return view('admin.transactions.index', [
            'transactions' => $query->latest('transaction_date')->latest('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function toggleExclude(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);
        $transaction->update(['excluded' => ! $transaction->excluded]);

        return back()->with('status', $transaction->excluded ? 'Transaction excluded.' : 'Transaction re-included.');
    }

    public function togglePaid(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);
        $transaction->update(['is_paid' => ! $transaction->is_paid]);

        return back()->with('status', 'Payment status updated.');
    }
}

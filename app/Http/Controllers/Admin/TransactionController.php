<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Transaction::class);

        $transactions = Transaction::query()
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25);

        return view('admin.transactions.index', ['transactions' => $transactions]);
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

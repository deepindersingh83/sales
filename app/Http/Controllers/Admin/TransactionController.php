<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
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
}

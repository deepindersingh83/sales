<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\Customer;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ActivityLogger;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The customer list synced from Xero contacts: search, filter, tag, and assign
 * an owner rep. Assigning a rep maintains a contact_id alias so every invoice
 * and credit note for that customer is credited to the rep.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => is_string($request->query('q')) ? trim($request->query('q')) : '',
            'owner' => is_string($request->query('owner')) ? $request->query('owner') : '',
            'tag' => is_string($request->query('tag')) ? $request->query('tag') : '',
            'status' => in_array($request->query('status'), ['active', 'archived', 'all'], true) ? $request->query('status') : 'active',
        ];

        $customers = Customer::query()
            ->with(['owner:id,name', 'tags'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['q']}%")
                ->orWhere('email', 'like', "%{$filters['q']}%")))
            ->when($filters['owner'] === 'none', fn ($q) => $q->whereNull('owner_user_id'))
            ->when(ctype_digit($filters['owner']), fn ($q) => $q->where('owner_user_id', (int) $filters['owner']))
            ->when($filters['tag'] !== '', fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('name', $filters['tag'])))
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'totals' => $this->totals($customers->getCollection()),
            'members' => $this->members(),
            'tags' => Tag::orderBy('name')->pluck('name'),
            'filters' => $filters,
            'canWrite' => (bool) $request->user()->currentRole()?->canWrite(),
        ]);
    }

    /** Assign (or clear) the owner rep and replace the customer's tags. */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->currentRole()?->canWrite(), 403);

        $data = $request->validate([
            'owner_user_id' => ['nullable', 'integer', Rule::in($this->members()->pluck('id')->all())],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($customer, $data) {
            $this->syncOwnerAlias($customer, $data['owner_user_id'] ?? null);
            $customer->syncTagNames(explode(',', (string) ($data['tags'] ?? '')));
        });

        $customer->load('owner:id,name', 'tags');
        app(ActivityLogger::class)->log('customer.updated', "Updated customer {$customer->name}.", $customer, [
            'rep' => $customer->owner?->name,
            'tags' => $customer->tags->pluck('name')->all(),
        ]);

        return back()->with('status', "Updated {$customer->name}.");
    }

    /**
     * Keep the customer's contact_id alias in step with its owner: create it on
     * first assignment, move it on reassignment, delete it when cleared.
     */
    protected function syncOwnerAlias(Customer $customer, ?int $ownerId): void
    {
        $alias = $customer->alias;

        if ($ownerId === null) {
            $alias?->delete();
            $customer->update(['owner_user_id' => null, 'alias_id' => null]);

            return;
        }

        if ($alias) {
            $alias->update(['user_id' => $ownerId]);
        } else {
            $alias = Alias::create([
                'user_id' => $ownerId,
                'match_field' => 'contact_id',
                'alias_value' => $customer->external_id,
                'match_type' => 'exact',
                'split_percent' => 100,
            ]);
        }

        $customer->update(['owner_user_id' => $ownerId, 'alias_id' => $alias->id]);
    }

    /**
     * Invoices, revenue and outstanding (ex tax, in each transaction's own
     * currency) per customer on the page, keyed by Xero contact id.
     *
     * @param  Collection<int, Customer>  $customers
     * @return Collection<string, array{invoices:int, revenue:float, outstanding:float, currencies:Collection<int, string>}>
     */
    protected function totals(Collection $customers): Collection
    {
        if ($customers->isEmpty()) {
            return collect();
        }

        return Transaction::query()
            ->where('excluded', false)
            ->whereIn('raw_data->contact_id', $customers->pluck('external_id')->all())
            ->get()
            ->groupBy(fn (Transaction $t) => (string) data_get($t->raw_data, 'contact_id'))
            ->map(fn (Collection $rows) => [
                'invoices' => $rows->count(),
                'revenue' => (float) $rows->sum(fn (Transaction $t) => (float) $t->amount),
                'outstanding' => (float) $rows->sum(fn (Transaction $t) => (float) $t->amount * $t->outstandingFraction()),
                'currencies' => $rows->pluck('currency')->unique()->values(),
            ]);
    }

    /** @return Collection<int, User> */
    protected function members(): Collection
    {
        return Workspace::withoutGlobalScopes()
            ->findOrFail(app(WorkspaceContext::class)->id())
            ->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name']);
    }
}

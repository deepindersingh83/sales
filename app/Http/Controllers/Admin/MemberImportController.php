<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Import\CsvReader;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Bulk member onboarding from a CSV (name,email,role,manager_email,salary).
 * Full Admin only. Idempotent: existing members are updated, new ones created
 * with a temporary password. A second pass wires up manager relationships once
 * every referenced manager exists.
 */
class MemberImportController extends Controller
{
    public function __construct(protected CsvReader $reader) {}

    public function create(): View
    {
        $this->authorizeFullAdmin();

        return view('admin.members.import', ['roles' => Role::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin();
        $workspace = $this->workspace();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = $this->reader->rows($request->file('file')->getRealPath());

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $emailToId = [];
        $pendingManagers = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // account for header row
            $email = strtolower(trim($this->col($row, ['email', 'e-mail'])));
            $name = trim($this->col($row, ['name', 'full name']));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Line {$line}: missing or invalid email.";
                $skipped++;

                continue;
            }

            $roleValue = $this->normaliseRole($this->col($row, ['role']));
            $salaryRaw = trim($this->col($row, ['salary', 'base salary']));
            $salary = is_numeric($salaryRaw) ? (float) $salaryRaw : null;

            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $name ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => Hash::make(Str::password(16)),
                ]);
            }

            $pivot = ['role' => $roleValue, 'salary' => $salary];

            if ($user->belongsToWorkspace($workspace)) {
                $workspace->users()->updateExistingPivot($user->id, $pivot);
                $updated++;
            } else {
                $workspace->users()->attach($user->id, $pivot);
                $created++;
            }

            $emailToId[$email] = $user->id;
            if ($managerEmail = strtolower(trim($this->col($row, ['manager_email', 'manager', 'reports to'])))) {
                $pendingManagers[$user->id] = $managerEmail;
            }
        }

        // Second pass: resolve manager relationships now that everyone exists.
        foreach ($pendingManagers as $userId => $managerEmail) {
            $managerId = $emailToId[$managerEmail]
                ?? $workspace->users()->where('email', $managerEmail)->value('users.id');

            if ($managerId && $managerId !== $userId) {
                $workspace->users()->updateExistingPivot($userId, ['manager_id' => $managerId]);
            }
        }

        $summary = "Import complete — {$created} added, {$updated} updated, {$skipped} skipped.";
        $redirect = redirect()->route('admin.members.index')->with('status', $summary);

        return $errors ? $redirect->with('import_errors', $errors) : $redirect;
    }

    /** Read the first present column from a list of candidate header names. */
    protected function col(array $row, array $candidates): string
    {
        foreach ($row as $key => $value) {
            if (in_array(strtolower(trim((string) $key)), $candidates, true)) {
                return (string) $value;
            }
        }

        return '';
    }

    protected function normaliseRole(string $raw): string
    {
        $raw = strtolower(trim(str_replace([' ', '-'], '_', $raw)));

        foreach (Role::cases() as $role) {
            if ($role->value === $raw || strtolower($role->label()) === strtolower(str_replace('_', ' ', $raw))) {
                return $role->value;
            }
        }

        return Role::Participant->value;
    }

    protected function authorizeFullAdmin(): void
    {
        abort_unless(request()->user()?->currentRole() === Role::FullAdmin, 403, 'Full Admin only.');
    }

    protected function workspace(): Workspace
    {
        return Workspace::withoutGlobalScopes()->findOrFail(app(WorkspaceContext::class)->id());
    }
}

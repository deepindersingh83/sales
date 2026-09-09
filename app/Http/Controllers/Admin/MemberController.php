<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\UsageMeter;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Workspace member + role management. Full Admin only — this is where the four
 * roles are actually assigned. Guards the "at least one Full Admin" invariant.
 */
class MemberController extends Controller
{
    public function index(): View
    {
        $this->authorizeFullAdmin();

        $members = $this->workspace()->users()->orderBy('name')->get();

        return view('admin.members.index', [
            'members' => $members,
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin();
        $workspace = $this->workspace();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        // Enforce the tier's payee cap (Free tier). Upgrading or starting a
        // trial lifts the cap.
        if (app(UsageMeter::class)->wouldExceedLimit($workspace)) {
            $limit = $workspace->payeeLimit();

            return back()->withErrors([
                'email' => "Your plan is limited to {$limit} members. Upgrade your subscription or start a trial to add more.",
            ]);
        }

        // Attach an existing user, or create a fresh login with a temp password.
        $user = User::where('email', $data['email'])->first();
        $tempPassword = null;

        if (! $user) {
            $tempPassword = Str::password(12);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
            ]);
        }

        if ($user->belongsToWorkspace($workspace)) {
            return back()->withErrors(['email' => 'That person is already a member.']);
        }

        $workspace->users()->attach($user->id, ['role' => $data['role']]);

        $flash = $tempPassword
            ? "Member added. Temporary password for {$user->email}: {$tempPassword} (share securely — shown once)."
            : 'Existing user added to the workspace.';

        return redirect()->route('admin.members.index')->with('status', $flash);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $this->authorizeFullAdmin();
        $workspace = $this->workspace();

        abort_unless($member->belongsToWorkspace($workspace), 404);

        $memberIds = $workspace->users()->pluck('users.id')->all();

        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'manager_id' => ['nullable', 'integer', Rule::in($memberIds), Rule::notIn([$member->id])],
            'salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Don't allow removing the last Full Admin via a demotion.
        if ($member->roleIn($workspace) === Role::FullAdmin && $data['role'] !== Role::FullAdmin->value
            && $this->fullAdminCount($workspace) <= 1) {
            return back()->withErrors(['role' => 'A workspace must keep at least one Full Admin.']);
        }

        $workspace->users()->updateExistingPivot($member->id, [
            'role' => $data['role'],
            'manager_id' => $data['manager_id'] ?? null,
            'salary' => $data['salary'] ?? null,
        ]);

        return redirect()->route('admin.members.index')->with('status', 'Member updated.');
    }

    public function destroy(User $member): RedirectResponse
    {
        $this->authorizeFullAdmin();
        $workspace = $this->workspace();

        abort_unless($member->belongsToWorkspace($workspace), 404);

        if ($member->roleIn($workspace) === Role::FullAdmin && $this->fullAdminCount($workspace) <= 1) {
            return back()->withErrors(['role' => 'A workspace must keep at least one Full Admin.']);
        }

        $workspace->users()->detach($member->id);

        return redirect()->route('admin.members.index')->with('status', 'Member removed.');
    }

    protected function authorizeFullAdmin(): void
    {
        abort_unless(request()->user()?->currentRole() === Role::FullAdmin, 403, 'Full Admin only.');
    }

    protected function workspace(): Workspace
    {
        return Workspace::withoutGlobalScopes()->findOrFail(app(WorkspaceContext::class)->id());
    }

    protected function fullAdminCount(Workspace $workspace): int
    {
        return $workspace->users()->wherePivot('role', Role::FullAdmin->value)->count();
    }
}

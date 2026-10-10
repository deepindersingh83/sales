<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ActivityLogger;
use App\Services\Billing\UsageMeter;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
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
            $user->sendEmailVerificationNotification();
        }

        if ($user->belongsToWorkspace($workspace)) {
            return back()->withErrors(['email' => 'That person is already a member.']);
        }

        // Anyone can sign up with someone else's address; only an account whose
        // owner has proved they control the inbox may be granted access.
        if (! $tempPassword && ! $user->hasVerifiedEmail()) {
            return back()->withErrors([
                'email' => 'An account with this email exists but its email address has not been verified. Ask them to verify it (or reset their password), then add them again.',
            ]);
        }

        $workspace->users()->attach($user->id, ['role' => $data['role']]);

        $logger->log('member.added', "Added {$user->email} as ".Role::from($data['role'])->label().'.', $user, [
            'role' => $data['role'],
            'new_account' => $tempPassword !== null,
        ]);

        $flash = $tempPassword
            ? "Member added. Temporary password for {$user->email}: {$tempPassword} (share securely — shown once)."
            : 'Existing user added to the workspace.';

        return redirect()->route('admin.members.index')->with('status', $flash);
    }

    public function update(Request $request, User $member, ActivityLogger $logger): RedirectResponse
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

        $before = $this->membershipState($workspace, $member);

        $workspace->users()->updateExistingPivot($member->id, [
            'role' => $data['role'],
            'manager_id' => $data['manager_id'] ?? null,
            'salary' => $data['salary'] ?? null,
        ]);

        $changes = ActivityLogger::changes($before, $this->membershipState($workspace, $member));

        // Salary is compensation data: record that it changed, not the figures.
        if (isset($changes['salary'])) {
            $changes['salary'] = ['changed' => true];
        }

        if ($changes !== []) {
            $logger->log(
                isset($changes['role']) ? 'member.role_changed' : 'member.updated',
                isset($changes['role'])
                    ? "Changed {$member->email}'s role from ".Role::from($changes['role']['old'])->label().' to '.Role::from($changes['role']['new'])->label().'.'
                    : "Updated {$member->email}'s membership.",
                $member,
                ['changes' => $changes],
            );
        }

        return redirect()->route('admin.members.index')->with('status', 'Member updated.');
    }

    public function destroy(User $member, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeFullAdmin();
        $workspace = $this->workspace();

        abort_unless($member->belongsToWorkspace($workspace), 404);

        if ($member->roleIn($workspace) === Role::FullAdmin && $this->fullAdminCount($workspace) <= 1) {
            return back()->withErrors(['role' => 'A workspace must keep at least one Full Admin.']);
        }

        // A removed member must stop earning: their aliases would keep crediting
        // deals to them, and reports pointing at them would keep paying them
        // manager overrides on every new run.
        DB::transaction(function () use ($workspace, $member) {
            Alias::where('user_id', $member->id)->delete();
            DB::table('workspace_user')
                ->where('workspace_id', $workspace->id)
                ->where('manager_id', $member->id)
                ->update(['manager_id' => null]);
            $workspace->users()->detach($member->id);
        });

        $logger->log('member.removed', "Removed {$member->email} from the workspace.", $member);

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

    /**
     * The member's role, manager and salary in this workspace.
     *
     * @return array{role: string|null, manager_id: int|null, salary: string|null}
     */
    protected function membershipState(Workspace $workspace, User $member): array
    {
        $pivot = DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $member->id)
            ->first(['role', 'manager_id', 'salary']);

        return [
            'role' => $pivot?->role,
            'manager_id' => $pivot?->manager_id !== null ? (int) $pivot->manager_id : null,
            'salary' => $pivot?->salary !== null ? (string) $pivot->salary : null,
        ];
    }

    protected function fullAdminCount(Workspace $workspace): int
    {
        return $workspace->users()->wherePivot('role', Role::FullAdmin->value)->count();
    }
}

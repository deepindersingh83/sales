<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Announcement::class);

        return view('admin.announcements.index', [
            'announcements' => Announcement::latest()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Announcement::class);

        return view('admin.announcements.form', ['announcement' => new Announcement]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Announcement::class);

        $announcement = Announcement::create($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement saved.');
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        return view('admin.announcements.form', ['announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $announcement->update($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement deleted.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'publish' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => 'all',
            'published_at' => $request->boolean('publish') ? now() : null,
        ];
    }
}

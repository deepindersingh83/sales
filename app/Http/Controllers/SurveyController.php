<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyController extends Controller
{
    /** Admin: manage surveys + see responses. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->currentRole()?->isAdmin(), 403);

        return view('admin.surveys.index', [
            'surveys' => Survey::withCount('responses')->with('responses.user')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->currentRole() === Role::FullAdmin, 403);

        $data = $request->validate(['question' => ['required', 'string', 'max:255']]);
        Survey::create($data);

        return redirect()->route('admin.surveys.index')->with('status', 'Survey created.');
    }

    public function destroy(Request $request, Survey $survey): RedirectResponse
    {
        abort_unless($request->user()->currentRole() === Role::FullAdmin, 403);
        $survey->delete();

        return redirect()->route('admin.surveys.index')->with('status', 'Survey deleted.');
    }

    /** Participant: answer an active survey. */
    public function respond(Request $request, Survey $survey): RedirectResponse
    {
        abort_unless($survey->is_active, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        SurveyResponse::updateOrCreate(
            ['survey_id' => $survey->id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null],
        );

        return back()->with('status', 'Thanks for your feedback!');
    }
}

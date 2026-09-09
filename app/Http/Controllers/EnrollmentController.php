<?php

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Models\Enrollment;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Plan enrollment with a typed-name e-signature (MVP). A formal e-signature
 * provider (e.g. DocuSign) is the Phase-2 upgrade; the enrollment record already
 * captures signature text + timestamp so swapping in a provider is additive.
 */
class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $plans = Plan::where('status', PlanStatus::Active)
            ->with(['terms' => fn ($q) => $q->orderByDesc('version')])
            ->get();

        $mine = Enrollment::where('user_id', $request->user()->id)
            ->get()
            ->keyBy('plan_id');

        return view('enrollments.index', ['plans' => $plans, 'mine' => $mine]);
    }

    public function sign(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($plan->status === PlanStatus::Active, 404);

        $data = $request->validate([
            'signature' => ['required', 'string', 'max:255'],
        ]);

        Enrollment::updateOrCreate(
            ['plan_id' => $plan->id, 'user_id' => $request->user()->id],
            [
                'signature' => $data['signature'],
                'signed_at' => now(),
                'enrolled_at' => now(),
            ],
        );

        return redirect()->route('enrollments.index')->with('status', "Enrolled in {$plan->name}.");
    }
}

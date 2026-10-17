<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reporting\MemberStatement;
use App\Services\Reporting\ReportScope;
use App\Support\WorkspaceContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Commission statements as PDF. A member may always download their own; any
 * admin role may download another member's, limited to the plans that admin
 * may view (ReportScope), so a hidden plan never leaks through a statement.
 */
class StatementController extends Controller
{
    public function pdf(Request $request, User $member, MemberStatement $statements, ReportScope $scope, WorkspaceContext $context): Response
    {
        $viewer = $request->user();
        $isSelf = $viewer->id === $member->id;

        if (! $isSelf) {
            abort_unless($viewer->currentRole()?->isAdmin() ?? false, 403);
            abort_unless($member->belongsToWorkspace($context->id()), 404);
        }

        [$from, $to] = $this->period($request);

        $data = $statements->build($member, $from, $to, $isSelf ? null : $scope->planIds());
        $data['workspace'] = $context->get();

        $filename = 'statement-'.Str::slug($member->name).'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return Pdf::loadView('statements.pdf', $data)->download($filename);
    }

    /**
     * The statement window: an explicit from/to range, else a calendar month
     * (?month=YYYY-MM), defaulting to the current month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function period(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ]);

        if (! empty($data['from'])) {
            return [CarbonImmutable::parse($data['from']), CarbonImmutable::parse($data['to'])];
        }

        $month = ! empty($data['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $data['month'])
            : CarbonImmutable::now();

        return [$month->startOfMonth(), $month->endOfMonth()];
    }
}

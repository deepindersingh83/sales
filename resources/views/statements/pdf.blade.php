@php
    $brand = $workspace?->displayName() ?? config('app.name');
    $brandColor = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $workspace?->brand_color) ? $workspace->brand_color : '#4f46e5';
    $money = fn ($amount) => number_format((float) $amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Commission statement — {{ $member->name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1e293b; margin: 0; }
        .header { border-bottom: 3px solid {{ $brandColor }}; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 18px; font-weight: bold; color: {{ $brandColor }}; }
        .muted { color: #64748b; }
        h1 { font-size: 14px; margin: 6px 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 4px; }
        td { padding: 4px; border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; border-top: 1px solid #cbd5e1; }
        .empty { padding: 10px 4px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $brand }}</div>
        <h1>Commission statement</h1>
        <div>{{ $member->name }} <span class="muted">· {{ $member->email }}</span></div>
        <div class="muted">Period {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }} · Generated {{ now()->format('j M Y H:i') }}</div>
    </div>

    <h2>Summary by plan</h2>
    @if ($summary->isEmpty())
        <div class="empty">No released credits or payouts in this period.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Currency</th>
                    <th class="num">Credited</th>
                    <th class="num">Commission</th>
                    <th class="num">Other rewards</th>
                    <th class="num">Adjustments</th>
                    <th class="num">Total payout</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary as $row)
                    <tr>
                        <td>{{ $row['plan'] }}</td>
                        <td>{{ $row['currency'] }}</td>
                        <td class="num">{{ $money($row['credited']) }}</td>
                        <td class="num">{{ $money($row['commission']) }}</td>
                        <td class="num">{{ $money($row['other']) }}</td>
                        <td class="num">{{ $money($row['adjustments']) }}</td>
                        <td class="num">{{ $money($row['total']) }}</td>
                    </tr>
                @endforeach
                @foreach ($totals as $currency => $total)
                    <tr class="total">
                        <td colspan="6">Total payout ({{ $currency }})</td>
                        <td class="num">{{ $currency }} {{ $money($total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Released credits</h2>
    @if ($credits->isEmpty())
        <div class="empty">No released credits in this period.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Plan</th>
                    <th class="num">Credited amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($credits as $credit)
                    <tr>
                        <td>{{ $credit->transaction?->reference() ?? '—' }}</td>
                        <td>{{ $credit->transaction?->customer() ?? '—' }}</td>
                        <td>{{ $credit->transaction?->transaction_date?->format('j M Y') ?? '—' }}</td>
                        <td>{{ $credit->calcRun?->plan?->name ?? '—' }}</td>
                        <td class="num">{{ $credit->currency }} {{ $money($credit->credited_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Released rewards</h2>
    @if ($rewards->isEmpty())
        <div class="empty">No released rewards in this period.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Reward</th>
                    <th>Plan</th>
                    <th>Date</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rewards as $reward)
                    <tr>
                        <td>
                            {{ $reward->reward_type->label() }}
                            @if (isset($reward->meta['label']))<span class="muted">— {{ $reward->meta['label'] }}</span>@endif
                            @if (isset($reward->meta['reason']))<span class="muted">— {{ $reward->meta['reason'] }}</span>@endif
                        </td>
                        <td>{{ $reward->plan?->name ?? '—' }}</td>
                        <td>{{ $reward->created_at?->format('j M Y') }}</td>
                        <td class="num">{{ $reward->computed_amount !== null ? $reward->currency.' '.$money($reward->computed_amount) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>

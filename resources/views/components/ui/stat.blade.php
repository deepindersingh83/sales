@props(['label', 'value', 'hint' => null, 'accent' => 'slate'])

@php
    $valueColor = $accent === 'green' ? 'text-emerald-600' : 'text-slate-900';
@endphp

<div class="bg-white rounded-xl border border-slate-200 shadow-card p-5">
    <div class="text-sm text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold {{ $valueColor }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-slate-400">{{ $hint }}</div>
    @endif
</div>

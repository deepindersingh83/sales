@props(['color' => 'slate'])

@php
    $map = [
        'slate' => 'bg-slate-100 text-slate-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'brand' => 'bg-brand-100 text-brand-700',
        'yellow' => 'bg-amber-100 text-amber-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'red' => 'bg-rose-100 text-rose-700',
        'rose' => 'bg-rose-100 text-rose-700',
    ];
    $classes = $map[$color] ?? $map['slate'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium $classes"]) }}>
    {{ $slot }}
</span>

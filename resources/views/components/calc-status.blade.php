@props(['status'])

@php
    $value = $status instanceof \App\Enums\CalcRunStatus ? $status->value : $status;
    $classes = match ($value) {
        'completed' => 'bg-green-100 text-green-800',
        'running' => 'bg-blue-100 text-blue-800',
        'queued' => 'bg-gray-100 text-gray-800',
        'failed' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex px-2 text-xs font-semibold rounded-full $classes"]) }}>
    {{ ucfirst($value) }}
</span>

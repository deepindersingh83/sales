@props(['status'])

@php
    $value = $status instanceof \App\Enums\DisputeStatus ? $status->value : $status;
    $classes = match ($value) {
        'open' => 'bg-yellow-100 text-yellow-800',
        'investigating' => 'bg-blue-100 text-blue-800',
        'resolved' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex px-2 text-xs font-semibold rounded-full $classes"]) }}>
    {{ ucfirst($value) }}
</span>

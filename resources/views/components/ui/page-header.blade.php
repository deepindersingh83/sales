@props(['title', 'subtitle' => null])

<div class="flex items-center justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-lg font-semibold text-slate-800 truncate">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-slate-500 truncate">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
    @endisset
</div>

<form method="GET" action="{{ route('admin.reports.crediting') }}" class="flex items-center gap-2">
    <label for="field" class="text-sm text-slate-600">Group by:</label>
    <select id="field" name="field" onchange="this.form.submit()" class="border-slate-300 rounded-lg shadow-sm text-sm">
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected($value === $field)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</form>

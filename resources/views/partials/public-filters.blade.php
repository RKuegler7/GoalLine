{{-- Search and filters for the public lists (GET, so the URL keeps them). $kinds: value => label; $durations likewise. --}}
@php $select = 'min-h-11 rounded-lg border border-line bg-white px-2.5 text-[13px] text-ink outline-none focus:border-ink/40'; @endphp
<form method="GET" action="{{ $action }}" class="space-y-2 rounded-2xl border border-line bg-white p-3" role="search" data-public-filters>
    <label for="public-q" class="sr-only">Search by name</label>
    <div class="flex gap-2">
        <input id="public-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="60" placeholder="Search by name" autocomplete="off"
            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-[15px] outline-none focus:border-ink/40">
        <button type="submit" class="min-h-11 rounded-full bg-ink px-4 text-[13px] font-semibold text-ivory">Search</button>
    </div>
    <div class="flex flex-wrap gap-2">
        <label class="sr-only" for="public-kind">{{ $kindLabel }}</label>
        <select id="public-kind" name="kind" class="{{ $select }}" onchange="this.form.requestSubmit()">
            <option value="">Any {{ Str::lower($kindLabel) }}</option>
            @foreach ($kinds as $value => $label)
                <option value="{{ $value }}" @selected(($filters['kind'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="public-when">When</label>
        <select id="public-when" name="when" class="{{ $select }}" onchange="this.form.requestSubmit()">
            <option value="">Any time</option>
            @foreach (App\Services\PublicListings::WHEN as $value => $label)
                <option value="{{ $value }}" @selected(($filters['when'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="public-size">Size</label>
        <select id="public-size" name="size" class="{{ $select }}" onchange="this.form.requestSubmit()">
            <option value="">Any size</option>
            @foreach (App\Services\PublicListings::SIZES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['size'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="public-duration">Length</label>
        <select id="public-duration" name="duration" class="{{ $select }}" onchange="this.form.requestSubmit()">
            <option value="">Any length</option>
            @foreach ($durations as $value => $label)
                <option value="{{ $value }}" @selected(($filters['duration'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

{{-- The people search box, used on the Friends page, Around the League, and a league's invite section. --}}
@props(['query' => '', 'league' => null, 'label' => 'Find people'])

<form method="GET" action="{{ route('people.search') }}" {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-white px-5 py-4']) }}>
    @if ($league)
        <input type="hidden" name="league" value="{{ $league->id }}">
    @endif
    <label for="people-q-{{ $league?->id ?? 'all' }}" class="eyebrow text-club">{{ $label }}</label>
    <div class="mt-2 flex gap-2">
        <input id="people-q-{{ $league?->id ?? 'all' }}" name="q" value="{{ $query }}" minlength="2" maxlength="255" required autocomplete="off" autocapitalize="none"
            placeholder="Name, @username, or exact email"
            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
        <button type="submit" class="rounded-lg bg-club px-3 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Search</button>
    </div>
</form>

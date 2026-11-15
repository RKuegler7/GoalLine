{{-- "Find a public GoalLeague" / "Find a public Challenge": a swipeable row of cards with "See all", at the top of each
     GoalLeagues tab. The same cards and listings as Explore. --}}
@php $tone = $kind === 'leagues' ? 'text-club' : 'text-gold-ink'; @endphp
<section aria-labelledby="{{ $id }}" class="mb-4" data-public-row="{{ $kind }}">
    <div class="mb-1.5 flex items-center justify-between gap-2">
        <h2 id="{{ $id }}" class="eyebrow {{ $tone }}">{{ $title }}</h2>
        <a href="{{ $seeAll }}" class="gl-tap relative text-[13px] font-semibold hover:underline {{ $tone }}">See all <span aria-hidden="true">&rarr;</span></a>
    </div>
    @if ($items->isEmpty())
        <p class="rounded-xl border border-line bg-white px-3 py-2.5 text-[13px] text-ink/75">{{ $empty }}</p>
    @else
        <ul class="-mx-4 flex snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]" tabindex="0" aria-label="{{ $title }}. Swipe for more.">
            @foreach ($items as $item)
                @if ($kind === 'leagues')
                    <x-public-league-card :league="$item" :example="auth()->user()->isDemo()" class="w-64 shrink-0 snap-start" />
                @else
                    <x-public-challenge-card :challenge="$item" :example="auth()->user()->isDemo()" class="w-64 shrink-0 snap-start" />
                @endif
            @endforeach
        </ul>
    @endif
</section>

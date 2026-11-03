<x-layouts.app :title="'GoalWeek '.$week.' Matchup · '.$league->name">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.show', $league) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $league->name }}</a>
            <div class="mt-6 flex items-center justify-between gap-3">
                @if ($week > 1)
                    <a href="{{ route('leagues.matchups', [$league, $week - 1]) }}" class="grid size-10 shrink-0 place-items-center rounded-full border border-ivory/40 text-ivory hover:bg-ivory hover:text-club" aria-label="GoalWeek {{ $week - 1 }} Matchup">&larr;</a>
                @else
                    <span class="size-10 shrink-0"></span>
                @endif
                <div class="min-w-0 text-center">
                    <p class="eyebrow text-mint">{{ $league->weekStartsOn($week)->format('M j') }}&ndash;{{ $league->weekEndsOn($week)->format('M j') }} &middot; {{ $final ? 'Final' : 'Live' }}</p>
                    <h1 class="mt-2 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">GoalWeek {{ $week }} Matchup</h1>
                    <p class="mt-2 text-sm text-ivory/75">{{ count($cards) }} {{ Str::plural('matchup', count($cards)) }} &middot; swipe to see them all</p>
                </div>
                @if ($week < $latest)
                    <a href="{{ route('leagues.matchups', [$league, $week + 1]) }}" class="grid size-10 shrink-0 place-items-center rounded-full border border-ivory/40 text-ivory hover:bg-ivory hover:text-club" aria-label="GoalWeek {{ $week + 1 }} Matchup">&rarr;</a>
                @else
                    <span class="size-10 shrink-0"></span>
                @endif
            </div>
        </div>
    </x-slot:hero>

    @if ($errors->getBag('dispute')->any())
        <p class="mb-4 rounded-xl bg-gold/15 px-4 py-3 text-sm text-ink">{{ $errors->getBag('dispute')->first() }}</p>
    @endif

    @if ($cards === [])
        <p class="rounded-2xl border border-line bg-white px-6 py-8 text-center text-ink/65">Matchups need at least two members. Invite your people from the league page.</p>
    @else
        {{-- Swipe (or use the arrows and dots) across matchups. Without JS it's a plain horizontal scroller. --}}
        <div data-carousel>
            <div class="relative flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-carousel-track>
                @foreach ($cards as $index => $card)
                    <x-matchup-card :card="$card" :league="$league" :week="$week" :final="$final"
                        id="matchup-{{ $index + 1 }}" data-carousel-slide
                        class="w-[88%] max-w-xl shrink-0 snap-center sm:w-[70%]" />
                @endforeach
            </div>

            @if (count($cards) > 1)
                <div class="mt-3 flex items-center justify-center gap-3">
                    <button type="button" data-carousel-prev class="grid size-9 place-items-center rounded-full border border-line bg-white text-club hover:border-club/40" aria-label="Previous matchup">&larr;</button>
                    <div class="flex items-center gap-1.5">
                        @foreach ($cards as $index => $card)
                            <a href="#matchup-{{ $index + 1 }}" data-carousel-dot class="size-2.5 rounded-full bg-ink/20 transition data-[active]:w-6 data-[active]:bg-club"
                                aria-label="{{ $card['home']['member']->user->name }} vs {{ $card['away']['member']->user->name ?? 'the league median' }}"></a>
                        @endforeach
                    </div>
                    <button type="button" data-carousel-next class="grid size-9 place-items-center rounded-full border border-line bg-white text-club hover:border-club/40" aria-label="Next matchup">&rarr;</button>
                </div>
            @endif
        </div>

        {{-- Every matchup on one screen. --}}
        <section class="mt-8">
            <h2 class="eyebrow text-club">Scoreboard</h2>
            <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($cards as $index => $card)
                    <li>
                        <a href="#matchup-{{ $index + 1 }}" data-carousel-jump="{{ $index }}" @class(['grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 py-3 text-sm hover:bg-mint/30', 'bg-mint/40' => $card['mine']])>
                            @foreach ([$card['home'], $card['away']] as $sideIndex => $side)
                                @if ($sideIndex === 1)
                                    <span class="text-xs text-ink/40">vs</span>
                                @endif
                                <span @class(['min-w-0', 'text-right' => $sideIndex === 1])>
                                    <span @class(['block truncate', 'font-semibold text-club' => $side && $side['outcome'] === App\MatchupOutcome::Win, 'text-ink' => ! $side || $side['outcome'] !== App\MatchupOutcome::Win])>
                                        {{ $side ? $side['member']->user->name : 'League median' }}
                                    </span>
                                    <span class="block text-xs text-ink/55 tabular-nums">
                                        @if ($side)
                                            {{ $side['share'] === null ? 'No GoalWeek' : round($side['share'] * 100).'%' }} &middot; {{ $side['result']?->label() ?? 'Short of the line' }}
                                        @else
                                            {{ $card['median'] === null ? '–' : round($card['median'], 1).' line pts' }}
                                        @endif
                                    </span>
                                </span>
                            @endforeach
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-8 rounded-2xl border border-line bg-white px-6 py-5 text-sm text-ink/70">
        <h2 class="eyebrow text-club">How a matchup is scored</h2>
        <ul class="mt-2 space-y-1.5">
            <li><span class="font-semibold text-ink">Your band</span> comes from the share of your own plan you finish: under 90% is Short of the line (0 pts), 90&ndash;100% In line (1), over 100% Past the line (2), 150% and up Smashed (3).</li>
            <li><span class="font-semibold text-ink">The matchup</span> compares bands, not raw points: the better band wins 2, the same band draws 1 each.</li>
            <li><span class="font-semibold text-ink">Your week</span> is line points + match points, up to 5. Results go final a day after the GoalWeek ends.</li>
        </ul>
    </section>
</x-layouts.app>

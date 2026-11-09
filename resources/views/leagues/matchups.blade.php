<x-layouts.app :title="'GoalWeek '.$week.' Matchup · '.$league->name">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('leagues.show', $league) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $league->name }}</a>

                {{-- League switcher, when you play in more than one league. --}}
                @if ($leagues->count() > 1)
                    <details class="relative" data-menu>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded-full border border-ivory/40 px-4 text-sm font-semibold text-ivory hover:bg-ivory/10 [&::-webkit-details-marker]:hidden">
                            Switch league <span aria-hidden="true">&#9662;</span>
                        </summary>
                        <ul class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
                            @foreach ($leagues as $other)
                                <li>
                                    <a href="{{ route('leagues.matchups', $other) }}" @class(['flex min-h-11 items-center px-4 text-ink hover:bg-mint/50', 'font-semibold text-club' => $other->is($league)]) @if ($other->is($league)) aria-current="page" @endif>{{ $other->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>

            <div class="mt-5 text-center">
                <p class="eyebrow text-mint">{{ $league->weekStartsOn($week)->format('M j') }}&ndash;{{ $league->weekEndsOn($week)->format('M j') }} &middot; {{ $final ? 'Final' : 'Live' }}</p>
                <h1 class="mt-2 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">GoalWeek {{ $week }} Matchup</h1>

                {{-- Week selector: past weeks are final and read only. --}}
                <details class="relative mt-3 inline-block text-left" data-menu>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded-full bg-ivory/15 px-4 text-sm font-semibold text-ivory hover:bg-ivory/25 [&::-webkit-details-marker]:hidden">
                        GoalWeek {{ $week }} of {{ $league->weeksCount() }} <span aria-hidden="true">&#9662;</span>
                    </summary>
                    <ul class="absolute left-1/2 z-30 mt-2 max-h-72 w-56 -translate-x-1/2 overflow-y-auto rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
                        @foreach (range($latest, 1) as $option)
                            <li>
                                <a href="{{ route('leagues.matchups', [$league, $option]) }}" @class(['flex min-h-11 items-center justify-between px-4 text-ink hover:bg-mint/50', 'font-semibold text-club' => $option === $week]) @if ($option === $week) aria-current="page" @endif>
                                    <span>GoalWeek {{ $option }}</span>
                                    <span class="text-xs text-ink/50">{{ $option === $latest && ! $final ? 'Live' : 'Final' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </div>
    </x-slot:hero>

    @if ($errors->getBag('dispute')->any())
        <p class="mb-4 rounded-xl bg-gold/15 px-4 py-3 text-sm text-ink">{{ $errors->getBag('dispute')->first() }}</p>
    @endif

    @if ($pages === [])
        <p class="rounded-2xl border border-line bg-white px-6 py-8 text-center text-ink/65">No matchups this week yet. Invite your people from the league page.</p>
    @else
        {{-- The pager: one page per matchup. Swipe, use the arrows or dots, or the left and right arrow keys.
             Without JavaScript it's a plain horizontal scroller with links. --}}
        <div data-pager class="-mx-4 sm:mx-0" aria-roledescription="carousel" aria-label="GoalWeek {{ $week }} matchups">
            <div class="flex items-center justify-between gap-3 px-4 sm:px-0">
                <button type="button" data-pager-prev class="grid size-11 shrink-0 place-items-center rounded-full border border-line bg-white text-club hover:border-club/40 disabled:opacity-40" aria-label="Previous matchup">&larr;</button>
                <div class="flex min-w-0 flex-col items-center gap-1.5">
                    <p class="text-sm font-semibold text-ink tabular-nums" data-pager-count aria-live="polite">1 of {{ count($pages) }}</p>
                    @if (count($pages) > 1)
                        <div class="flex flex-wrap items-center justify-center gap-1">
                            @foreach ($pages as $index => $page)
                                <a href="#matchup-{{ $index + 1 }}" data-pager-dot class="group grid h-6 min-w-6 place-items-center" aria-label="Matchup {{ $index + 1 }}: {{ $page['card']['home']['member']->user->name }} vs {{ $page['card']['away']['member']->user->name ?? 'the league median' }}">
                                    <span class="block size-2.5 rounded-full bg-ink/20 transition-all group-data-[active]:w-6 group-data-[active]:bg-club"></span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                <button type="button" data-pager-next class="grid size-11 shrink-0 place-items-center rounded-full border border-line bg-white text-club hover:border-club/40 disabled:opacity-40" aria-label="Next matchup">&rarr;</button>
            </div>

            {{-- On phones, one player's tasks at a time. --}}
            <div data-player-toggle hidden class="sticky top-0 z-20 mt-3 bg-ivory/95 px-4 py-2 backdrop-blur md:hidden">
                <div class="grid grid-cols-2 gap-1 rounded-full bg-white p-1 ring-1 ring-line" role="tablist" aria-label="Whose tasks to show">
                    @foreach ([0, 1] as $sideIndex)
                        <button type="button" role="tab" data-player-tab="{{ $sideIndex }}" aria-selected="{{ $sideIndex === 0 ? 'true' : 'false' }}"
                            class="min-h-11 truncate rounded-full px-3 text-sm font-semibold text-ink/60 aria-selected:bg-club aria-selected:text-ivory"></button>
                    @endforeach
                </div>
            </div>

            <div data-pager-track class="mt-3 flex snap-x snap-mandatory items-start overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" tabindex="0" aria-label="Matchup pages">
                @foreach ($pages as $index => $page)
                    <x-matchup-page :page="$page" :league="$league" :week="$week" :index="$index" :count="count($pages)" />
                @endforeach
            </div>
        </div>

        {{-- Every matchup on one screen. --}}
        <section class="mt-8">
            <h2 class="eyebrow text-club">Scoreboard</h2>
            <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($cards as $index => $card)
                    <li>
                        <a href="#matchup-{{ $index + 1 }}" data-pager-jump="{{ $index }}" @class(['grid min-h-11 grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 py-3 text-sm hover:bg-mint/30', 'bg-mint/40' => $card['mine']])>
                            @foreach ([$card['home'], $card['away']] as $sideIndex => $side)
                                @if ($sideIndex === 1)
                                    <span class="text-xs text-ink/40">vs</span>
                                @endif
                                <span @class(['min-w-0', 'text-right' => $sideIndex === 1])>
                                    <span @class(['block truncate', 'font-semibold text-club' => $side && $side['outcome']->isWin(), 'text-ink' => ! $side || ! $side['outcome']->isWin()])>
                                        {{ $side ? $side['member']->user->name : 'League median' }}
                                    </span>
                                    <span class="block text-xs text-ink/55 tabular-nums">
                                        @if ($side)
                                            {{ $side['percent'] === null ? 'No GoalWeek' : $side['percent'].'%' }} &middot; {{ $side['result']?->label() ?? 'Short of the line' }}{{ $side['outcome']->isPhotoFinish() ? ' · photo finish' : '' }}
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
            <li><span class="font-semibold text-ink">The matchup</span> compares bands first: a higher band is a Win, 3 match points to 0.</li>
            <li><span class="font-semibold text-ink">Photo finish</span>: in the same band, the higher credited percent of plan (a whole number) takes a Close win for 2 and the other a Close loss for 1. Exactly the same percent is a Tie, 1 each. Held disputed points don&rsquo;t count, and an unproven Smashed week is capped at 149%.</li>
            <li><span class="font-semibold text-ink">Your week</span> is line points + match points, 0 to 6. Results go final a day after the GoalWeek ends.</li>
            <li><span class="font-semibold text-ink">Projected, on current pace</span> divides the points you&rsquo;ve earned by the points due so far. It&rsquo;s a guide, not a score.</li>
        </ul>
    </section>

    {{-- The task sheet: a bottom sheet on phones, a dialog on wider screens. Filled when a task is tapped. --}}
    <dialog data-task-sheet aria-labelledby="task-sheet-title" class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-3xl">
        <div class="flex justify-between gap-3 px-5 pt-3">
            <span class="mx-auto mt-1 h-1.5 w-10 rounded-full bg-ink/15 sm:hidden" aria-hidden="true"></span>
        </div>
        <form method="dialog" class="absolute top-3 right-3">
            <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/60 hover:bg-ivory" aria-label="Close">&times;</button>
        </form>
        <div data-task-sheet-body class="max-h-[80dvh] overflow-y-auto px-5 pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))]"></div>
    </dialog>
</x-layouts.app>

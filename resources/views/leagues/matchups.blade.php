<x-layouts.app :title="'GoalWeek '.$week.' Matchup · '.$league->name">
    <x-slot:hero>
        <x-page-header :title="'GoalWeek '.$week.' Matchup'" :back="route('leagues.show', $league)" :back-label="$league->name"
            :subtitle="$league->weekStartsOn($week)->format('M j').'–'.$league->weekEndsOn($week)->format('M j').' · '.($final ? 'Final' : 'Live')">
            {{-- Week selector: past weeks are final and read only. --}}
            <details class="relative" data-menu data-action-menu>
                <summary class="gl-tap relative inline-flex min-h-9 cursor-pointer list-none items-center gap-1 rounded-full bg-ivory/15 px-3 text-[13px] font-semibold text-ivory hover:bg-ivory/25 [&::-webkit-details-marker]:hidden">
                    Wk {{ $week }}<span class="sr-only"> of {{ $league->weeksCount() }}, choose a GoalWeek</span> <x-icon name="chevron-down" class="size-3.5" />
                </summary>
                <ul class="absolute right-0 z-50 mt-1.5 max-h-72 w-52 overflow-y-auto rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
                    @foreach (range($latest, 1) as $option)
                        <li>
                            <a href="{{ route('leagues.matchups', [$league, $option]) }}" @class(['flex min-h-11 items-center justify-between px-3.5 text-ink hover:bg-mint/50', 'font-semibold text-club' => $option === $week]) @if ($option === $week) aria-current="page" @endif>
                                <span>GoalWeek {{ $option }}</span>
                                <span class="text-xs text-ink/70">{{ $option === $latest && ! $final ? 'Live' : 'Final' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>

            {{-- League switcher, when you play in more than one league. --}}
            @if ($leagues->count() > 1)
                <details class="relative" data-menu data-action-menu>
                    <summary class="gl-tap relative inline-flex min-h-9 cursor-pointer list-none items-center gap-1 rounded-full bg-ivory px-3 text-[13px] font-semibold text-club hover:bg-white [&::-webkit-details-marker]:hidden">
                        <span class="sm:hidden">League</span><span class="hidden sm:inline">Switch league</span> <x-icon name="chevron-down" class="size-3.5" />
                    </summary>
                    <ul class="absolute right-0 z-50 mt-1.5 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
                        @foreach ($leagues as $other)
                            <li>
                                <a href="{{ route('leagues.matchups', $other) }}" @class(['flex min-h-11 items-center px-3.5 text-ink hover:bg-mint/50', 'font-semibold text-club' => $other->is($league)]) @if ($other->is($league)) aria-current="page" @endif>{{ $other->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </x-page-header>
    </x-slot:hero>

    @if ($errors->getBag('dispute')->any())
        <p class="mb-3 rounded-xl bg-gold/15 px-3 py-2 text-[13px] text-ink">{{ $errors->getBag('dispute')->first() }}</p>
    @endif

    @if ($pages === [])
        {{-- (Before kickoff there's no matchup page: the controller sends you to the league with the kickoff date.) --}}
        <x-empty-state message="No opponent yet this week. Invite your people and the matchups start." action="Invite friends" :href="route('leagues.show', $league).'#invite'" />
    @else
        {{-- The pager: one page per matchup, yours first. Swipe, tap the arrows or dots, or use the arrow keys.
             The top bar and tab bar stay put, and the back link leads to the league. --}}
        <div data-pager class="-mx-4 sm:mx-0" aria-roledescription="carousel" aria-label="GoalWeek {{ $week }} matchups" data-first-content>
            <div class="flex items-center justify-between gap-2 px-4 sm:px-0">
                <button type="button" data-pager-prev class="gl-tap relative grid size-9 shrink-0 place-items-center rounded-full border border-line bg-white text-club hover:border-club/40 disabled:opacity-40" aria-label="Previous matchup"><x-icon name="chevron-left" class="size-4" /></button>
                <div class="flex min-w-0 items-center gap-2">
                    @if (count($pages) > 1)
                        <div class="flex items-center gap-0.5">
                            @foreach ($pages as $index => $page)
                                <a href="#matchup-{{ $index + 1 }}" data-pager-dot class="group grid h-6 min-w-5 place-items-center" aria-label="Matchup {{ $index + 1 }}: {{ $page['card']['home']['member']->user->name }} vs {{ $page['card']['away']['member']->user->name ?? 'the league median' }}">
                                    <span class="block size-2 rounded-full bg-ink/20 transition-all group-data-[active]:w-5 group-data-[active]:bg-club"></span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-[13px] font-semibold text-ink tabular-nums" data-pager-count aria-live="polite">1 of {{ count($pages) }}</p>
                </div>
                <button type="button" data-pager-next class="gl-tap relative grid size-9 shrink-0 place-items-center rounded-full border border-line bg-white text-club hover:border-club/40 disabled:opacity-40" aria-label="Next matchup"><x-icon name="chevron-right" class="size-4" /></button>
            </div>

            @php
                $dayList = collect($pages)->flatMap(fn ($page) => collect($page['sides'])->pluck('days'))->first(fn ($days) => ! empty($days)) ?? [];
                $todayIndex = collect($dayList)->search(fn ($day) => $day['isToday']);
                $showWeek = $final || $todayIndex === false;
            @endphp

            {{-- Live numbers that stay in view while you scroll: the strip for the matchup on screen, and the day chips
                 (they choose the day on every page). Outside the pager, so they can stick under the top bar. --}}
            <div data-pager-sticky class="sticky top-[calc(3.25rem+env(safe-area-inset-top))] z-20 mt-2 border-y border-line bg-ivory/95 px-4 py-1.5 backdrop-blur sm:rounded-xl sm:border sm:px-3">
                @foreach ($pages as $index => $page)
                    <x-matchup-strip :page="$page" :index="$index" />
                @endforeach

                @if ($dayList !== [])
                    <div class="mt-1.5 flex items-center gap-1" data-day-controls>
                        <div class="flex flex-1 gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Day">
                            @foreach ($dayList as $dayIndex => $day)
                                <button type="button" data-day-chip="{{ $dayIndex }}" role="tab" aria-selected="{{ ! $showWeek && $dayIndex === $todayIndex ? 'true' : 'false' }}"
                                    class="min-h-8 shrink-0 rounded-full px-2 text-[11px] font-semibold text-ink/70 ring-1 ring-line ring-inset aria-selected:bg-club aria-selected:text-ivory aria-selected:ring-club">
                                    {{ $day['isToday'] ? 'Today' : $day['label'] }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button" data-day-all aria-pressed="{{ $showWeek ? 'true' : 'false' }}"
                            class="min-h-8 shrink-0 rounded-full px-2.5 text-[11px] font-semibold text-club ring-1 ring-club/30 ring-inset aria-pressed:bg-club aria-pressed:text-ivory">Week</button>
                    </div>
                @endif
            </div>

            {{-- The pager scrolls sideways only; each page grows to its own height, so the page itself scrolls down. --}}
            <div data-pager-track class="relative mt-2 flex snap-x snap-mandatory items-start overflow-x-auto overflow-y-clip overscroll-x-contain [touch-action:pan-x_pan-y_pinch-zoom] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" tabindex="0" aria-label="Matchup pages">
                @foreach ($pages as $index => $page)
                    <x-matchup-page :page="$page" :league="$league" :week="$week" :index="$index" :count="count($pages)" />
                @endforeach
            </div>
        </div>

        {{-- Every matchup on one screen. --}}
        <section class="mt-4">
            <h2 class="eyebrow text-club">Scoreboard</h2>
            <ul class="mt-1.5 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                @foreach ($cards as $index => $card)
                    <li>
                        <a href="#matchup-{{ $index + 1 }}" data-pager-jump="{{ $index }}" @class(['grid min-h-11 grid-cols-[1fr_auto_1fr] items-center gap-2 px-3 py-1.5 text-[13px] hover:bg-mint/30', 'bg-mint/40' => $card['mine']])>
                            @foreach ([$card['home'], $card['away']] as $sideIndex => $side)
                                @if ($sideIndex === 1)
                                    <span class="text-xs text-ink/70">vs</span>
                                @endif
                                <span @class(['min-w-0', 'text-right' => $sideIndex === 1])>
                                    <span @class(['block truncate', 'font-semibold text-club' => $side && $side['outcome']->isWin(), 'text-ink' => ! $side || ! $side['outcome']->isWin()])>
                                        {{ $side ? $side['member']->user->name : 'League median' }}
                                    </span>
                                    <span class="block truncate text-[11px] text-ink/70 tabular-nums">
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

    <details class="group mt-3 rounded-xl border border-line bg-white text-[13px] text-ink/70">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-3 [&::-webkit-details-marker]:hidden"><h2 class="eyebrow text-club">How a matchup is scored</h2><x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" /></summary>
        <ul class="space-y-1.5 border-t border-line px-3 py-2">
            <li><span class="font-semibold text-ink">Your band</span> comes from the share of your own plan you finish: under 90% is Short of the line (0 pts), 90&ndash;100% In line (1), over 100% Past the line (2), 150% and up Smashed (3).</li>
            <li><span class="font-semibold text-ink">The matchup</span> compares bands first: a higher band is a Win, 3 match points to 0.</li>
            <li><span class="font-semibold text-ink">Photo finish</span>: in the same band, the higher credited percent of plan (a whole number) takes a Close win for 2 and the other a Close loss for 1. Exactly the same percent is a Tie, 1 each. Held disputed points don&rsquo;t count, and an unproven Smashed week is capped at 149%.</li>
            <li><span class="font-semibold text-ink">Your week</span> is line points + match points, 0 to 6. Results go final a day after the GoalWeek ends.</li>
            <li><span class="font-semibold text-ink">Projected, on current pace</span> divides the points you&rsquo;ve earned by the points due so far. It&rsquo;s a guide, not a score.</li>
        </ul>
    </details>

    {{-- The task sheet: a bottom sheet on phones, a dialog on wider screens. Filled when a task is tapped. --}}
    <dialog data-task-sheet aria-labelledby="task-sheet-title" class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-3xl">
        <div class="flex justify-between gap-3 px-5 pt-3">
            <span class="mx-auto mt-1 h-1.5 w-10 rounded-full bg-ink/15 sm:hidden" aria-hidden="true"></span>
        </div>
        <form method="dialog" class="absolute top-3 right-3">
            <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/70 hover:bg-ivory" aria-label="Close">&times;</button>
        </form>
        <div data-task-sheet-body class="max-h-[80dvh] overflow-y-auto px-5 pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))]"></div>
    </dialog>
</x-layouts.app>

@php
    $record = $stats->leagueRecord();
    $today = App\Clock::today();
    $daysLeftInWeek = 7 - $today->dayOfWeekIso;
@endphp

<x-layouts.app title="Clubhouse · GoalLine">
    <x-slot:hero>
        <x-page-header :title="$greeting.', '.Str::before(auth()->user()->name, ' ').'.'"
            :eyebrow="now(App\Clock::timezone())->format('D, M j').' · '.($daysLeftInWeek === 0 ? 'GoalWeek ends tonight' : 'GoalWeek ends Sunday, '.$daysLeftInWeek.' '.Str::plural('day', $daysLeftInWeek).' left')">
            <x-action-menu label="New">
                <a href="{{ route('goals.create') }}"><x-icon name="plus" class="size-4 text-club" /> Set a new goal</a>
                <a href="{{ route('leagues.create') }}"><x-icon name="leagues" class="size-4 text-club" /> Start a GoalLeague</a>
                <a href="{{ route('challenges.create') }}"><x-icon name="flag" class="size-4 text-gold" /> Start a challenge</a>
                <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join"><x-icon name="users" class="size-4 text-club" /> Join with a code</a>
            </x-action-menu>
        </x-page-header>

        {{-- Progress at a glance: one row of small stats and the streak. --}}
        <div class="mx-auto -mt-1 max-w-5xl px-4 pb-3">
            <dl class="grid grid-cols-3 gap-1.5">
                @foreach ([
                    ['This week', $weekPoints, Str::plural('star', $weekPoints)],
                    ['Total', $stats->points(), Str::plural('star', $stats->points())],
                    ['Record', $record['wins'].'–'.$record['ties'].'–'.$record['losses'], ''],
                ] as [$label, $value, $unit])
                    <div class="rounded-lg bg-ivory/10 px-2 py-1.5 ring-1 ring-ivory/15 ring-inset">
                        <dt class="truncate text-[11px] font-semibold text-mint">{{ $label }}</dt>
                        <dd class="truncate text-[17px] leading-tight font-bold text-ivory tabular-nums">{{ $value }} <span class="text-[11px] font-medium text-ivory">{{ $unit }}</span></dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-weeks-on-the-line :weeks="$stats->weeksOnTheLine()" :best="$stats->bestStreak()" />
                <x-daily-streak :streak="$streak" />
            </div>
        </div>
    </x-slot:hero>

    @include('partials.clubhouse-tabs', ['active' => 'now'])

    <div class="grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4" data-first-content>
            @include('partials.league-setup-cards')

            {{-- Goal Day: the check-in waits here until it's answered; the countdown shows in the final two weeks. --}}
            @if ($goalDayPending)
                <a href="{{ route('goal-day.show', $goalDayPending) }}" class="flex items-center justify-between gap-3 rounded-xl bg-gold px-3 py-2.5 text-ink hover:brightness-105" data-goal-day-prompt>
                    <span class="min-w-0">
                        <span class="eyebrow block text-[11px]">Goal Day</span>
                        <span class="block truncate text-[15px] font-bold">Did you reach "{{ $goalDayPending->title }}"?</span>
                    </span>
                    <span class="shrink-0 font-semibold">Check in &rarr;</span>
                </a>
            @elseif ($goalDayUpcoming)
                @php $daysToGoalDay = $goalDayUpcoming->daysRemaining(); @endphp
                <a href="{{ route('goals.show', $goalDayUpcoming) }}" class="flex items-center justify-between gap-3 rounded-xl border border-gold bg-gold/15 px-3 py-2 text-[13px] text-ink hover:bg-gold/25" data-goal-day-banner>
                    <span class="min-w-0 truncate"><span class="font-bold">Goal Day is in {{ $daysToGoalDay }} {{ Str::plural('day', $daysToGoalDay) }}</span> &middot; {{ $goalDayUpcoming->title }}</span>
                    <span class="shrink-0 font-semibold text-club">Finish strong &rarr;</span>
                </a>
            @endif

            @if ($endedGoals > 0)
                <a href="{{ route('dashboard', ['tab' => 'history']) }}" class="flex items-center justify-between gap-3 rounded-xl border border-line bg-white px-3 py-2 text-[13px] hover:border-club/40" data-ended-goals>
                    <span class="text-ink/80">{{ $endedGoals }} {{ Str::plural('goal', $endedGoals) }} reached {{ $endedGoals === 1 ? 'its' : 'their' }} date. Mark {{ $endedGoals === 1 ? 'it' : 'them' }} done or set a new date in History.</span>
                    <span class="shrink-0 font-semibold text-club">History &rarr;</span>
                </a>
            @endif

            @if ($goals->isEmpty())
                <x-empty-state message="What do you want to accomplish? Name a goal and its date, and GoalLine builds your plan." action="Set your first goal" :href="route('goals.create')" />
            @else
                {{-- Today first: one-tap check-offs, no reload. --}}
                <x-todays-gameplan :gameplan="$gameplan" />
            @endif

            {{-- Your GoalLeagues, with this week's matchup. --}}
            <section aria-labelledby="clubhouse-leagues">
                <div class="mb-1.5 flex items-center justify-between">
                    <h2 id="clubhouse-leagues" class="eyebrow text-club">GoalLeagues</h2>
                    <a href="{{ route('leagues.index') }}" class="gl-tap relative text-[13px] font-semibold text-club hover:underline">All</a>
                </div>
                @include('partials.clubhouse-leagues')
            </section>

            @foreach ($soloGoals->reject(fn ($goal) => $chosenSolo->contains($goal->id)) as $goal)
                @include('partials.play-with-friends', ['goal' => $goal])
            @endforeach

            @if ($shareable)
                <a href="{{ $shareable['url'] }}" class="flex items-center justify-between gap-3 rounded-xl border border-gold/50 bg-gold/15 px-3 py-2 text-[13px] transition hover:bg-gold/25">
                    <span class="flex min-w-0 items-center gap-2 text-ink"><x-icon name="highlights" class="size-4 shrink-0 text-ink/70" /> <span class="truncate">{{ $shareable['label'] }}</span></span>
                    <span class="shrink-0 font-semibold text-ink">Share &rarr;</span>
                </a>
            @endif

            @if ($kickoff->isVisible())
                <x-kickoff-checklist :kickoff="$kickoff" />
            @endif

            @if ($goals->isNotEmpty())
                {{-- Each goal's GoalWeek, collapsed to one line until you want the detail. --}}
                <section aria-labelledby="clubhouse-goals">
                    <div class="mb-1.5 flex items-center justify-between">
                        <h2 id="clubhouse-goals" class="eyebrow text-club">This GoalWeek</h2>
                        <a href="{{ route('goals.create') }}" class="gl-tap relative text-[13px] font-semibold text-club hover:underline">+ Add a goal</a>
                    </div>
                    <div class="space-y-2">
                        @foreach ($goals as $goal)
                            @php
                                $week = $goal->currentWeek();
                                $percent = $week?->creditedPercent() ?? 0;
                            @endphp
                            <details class="group rounded-xl border border-line bg-white">
                                <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 px-3 py-2 [&::-webkit-details-marker]:hidden">
                                    {{-- Its own region, so a check-in updates the band here without closing the details. --}}
                                    <span class="min-w-0 flex-1" data-region="goal-band-{{ $goal->id }}">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="truncate text-[15px] font-bold text-club">{{ $goal->title }}</span>
                                            <span class="shrink-0 text-xs font-semibold text-ink tabular-nums">{{ $percent }}%</span>
                                        </span>
                                        @if ($week)
                                            <x-band-track :week="$week" compact />
                                        @else
                                            <x-progress-bar :percent="$percent" class="mt-1.5" label="{{ $goal->title }}: {{ $percent }}% of plan" />
                                        @endif
                                    </span>
                                    <x-icon name="chevron-down" class="size-4 shrink-0 text-ink/70 transition group-open:rotate-180" />
                                </summary>
                                <div class="border-t border-line">
                                    <x-clubhouse-goal :goal="$goal" class="rounded-none border-0" />
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            <details class="group rounded-xl border border-line bg-white">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-3 [&::-webkit-details-marker]:hidden">
                    <span class="eyebrow text-club">Week at a glance</span>
                    <x-icon name="chevron-down" class="size-4 text-ink/70 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-line p-2"><x-week-at-a-glance :days="$activity" /></div>
            </details>
        </div>

        <aside class="space-y-4">

            {{-- Friends: the latest three, with the full feed one tap away. --}}
            <section id="friends-feed" class="scroll-mt-24" aria-labelledby="clubhouse-friends">
                <div class="mb-1.5 flex items-center justify-between">
                    <h2 id="clubhouse-friends" class="eyebrow text-club">Friends</h2>
                    <a href="{{ route('highlights.index') }}" class="gl-tap relative text-[13px] font-semibold text-club hover:underline">See all</a>
                </div>
                <div class="divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                    @forelse (collect($friendFeed->items())->take(3) as $highlight)
                        <a href="{{ $highlight->league_id ? route('leagues.feed', $highlight->league_id) : route('highlights.index') }}#highlight-{{ $highlight->id }}" class="flex items-center gap-2.5 px-3 py-2 hover:bg-mint/40">
                            <x-avatar :user="$highlight->user" class="size-7 shrink-0 text-[11px]" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px]"><span class="font-semibold text-ink">{{ Str::before($highlight->user->name, ' ') }}</span> <span class="text-ink/70">&middot; {{ $highlight->kind->label() }}</span></span>
                                <span class="block truncate text-xs text-ink/70">{{ $highlight->isPost() ? $highlight->detail : $highlight->headline }}@if ($highlight->caption) &middot; &ldquo;{{ $highlight->caption }}&rdquo;@endif</span>
                            </span>
                            @if ($highlight->photoUrl())
                                <img src="{{ $highlight->photoUrl() }}" alt="" class="size-10 shrink-0 rounded-lg object-cover" loading="lazy" width="40" height="40">
                            @endif
                        </a>
                    @empty
                        @if (auth()->user()->friendIds()->isEmpty() && ! $friendFeed->previousPageUrl())
                            <x-empty-state class="rounded-none border-0" message="Goals stick better with company. Add a friend, and their check-ins and wins show up here." action="Add a friend" :href="route('friends.index')" />
                        @else
                            <x-empty-state class="rounded-none border-0" message="Quiet for now. When friends show their work or have a great week, it lands here. Why not go first?" action="Post a highlight" :href="route('highlights.create')" />
                        @endif
                    @endforelse
                </div>
            </section>

            <x-scoring-help />

            <x-install-app />
            <x-push-nudge />
        </aside>
    </div>
</x-layouts.app>

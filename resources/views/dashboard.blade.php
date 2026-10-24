@php
    $record = $stats->leagueRecord();
    $today = App\Clock::today();
    $daysLeftInWeek = 7 - $today->dayOfWeekIso;
@endphp

<x-layouts.app title="Clubhouse · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-4 pb-10">
            <p class="eyebrow text-mint">
                {{ now(App\Clock::timezone())->format('l, F j') }} &middot;
                {{ $daysLeftInWeek === 0 ? 'GoalWeek ends tonight' : 'GoalWeek ends Sunday, '.$daysLeftInWeek.' '.Str::plural('day', $daysLeftInWeek).' left' }}
            </p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">
                {{ $greeting }}, {{ Str::before(auth()->user()->name, ' ') }}.
            </h1>

            <x-daily-streak :streak="$streak" class="mt-4" />

            {{-- Scoreboard. --}}
            <dl class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([
                    ['This GoalWeek', $weekPoints, Str::plural('point', $weekPoints).' so far'],
                    ['Total points', $stats->points(), 'all time'],
                    ['In-line streak', $stats->currentStreak(), Str::plural('GoalWeek', $stats->currentStreak()).' in a row'],
                    ['GoalLeague record', $record['wins'].'–'.$record['ties'].'–'.$record['losses'], 'wins, ties, losses'],
                ] as [$label, $value, $note])
                    <div class="rounded-xl bg-ivory/10 px-4 py-3 ring-1 ring-ivory/15 ring-inset">
                        <dt class="eyebrow text-[0.62rem] text-mint">{{ $label }}</dt>
                        <dd class="mt-1 font-serif text-3xl font-medium text-ivory tabular-nums">{{ $value }}</dd>
                        <dd class="text-xs text-ivory/65">{{ $note }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-slot:hero>

    @if ($shareable)
        <a href="{{ $shareable['url'] }}" class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-gold/50 bg-gold/15 px-5 py-3 transition hover:bg-gold/25">
            <span class="flex items-center gap-3 text-ink">
                <x-icon name="highlights" class="size-5 text-ink/70" />
                {{ $shareable['label'] }}
            </span>
            <span class="shrink-0 text-sm font-semibold text-ink">Share &rarr;</span>
        </a>
    @endif

    <x-install-app class="mb-6" />
    <x-push-nudge class="mb-6" />

    @if ($kickoff->isVisible())
        <x-kickoff-checklist :kickoff="$kickoff" class="mb-6" />
    @endif

    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        {{-- This GoalWeek, goal by goal. --}}
        <div class="min-w-0 space-y-6">
            @if ($goals->isEmpty())
                <section class="overflow-hidden rounded-2xl border border-line bg-white">
                    <div class="relative h-36 overflow-hidden bg-club">
                        <x-field-backdrop class="absolute inset-0 size-full" />
                    </div>
                    <x-goal-line />
                    <div class="px-8 py-10 text-center">
                        <p class="eyebrow text-club">Your goal</p>
                        <h2 class="mt-3 font-serif text-2xl font-medium text-club">What do you want to accomplish?</h2>
                        <p class="mx-auto mt-2 max-w-md text-ink/70">Name a goal and its date, and GoalLine will build your personal roadmap.</p>
                        <a href="{{ route('goals.create') }}" class="mt-6 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory transition hover:bg-club-dark">
                            Set your first goal
                        </a>
                    </div>
                </section>
            @else
                <x-todays-gameplan :gameplan="$gameplan" />

                <div class="flex items-end justify-between gap-4">
                    <h2 class="eyebrow text-club">This GoalWeek</h2>
                    <a href="{{ route('goals.create') }}" class="text-sm font-semibold text-club hover:underline">+ Add a goal</a>
                </div>
                @foreach ($goals as $goal)
                    <x-clubhouse-goal :goal="$goal" />
                @endforeach
            @endif

            <x-week-at-a-glance :days="$activity" />
        </div>

        <aside class="space-y-6">
            {{-- GoalLeagues. --}}
            <section>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="eyebrow text-club">GoalLeagues</h2>
                    <a href="{{ route('leagues.index') }}" class="text-sm font-semibold text-club hover:underline">All</a>
                </div>
                @forelse ($leagues as $snapshot)
                    <x-league-snapshot :snapshot="$snapshot" class="mt-3" />
                @empty
                    <div class="mt-3 rounded-2xl border border-line bg-white px-5 py-4">
                        <p class="text-ink/70">Go head-to-head every GoalWeek with friends, or take on a challenge together.</p>
                        <div class="mt-4 grid gap-2">
                            <a href="{{ route('leagues.create') }}" class="flex items-center justify-center gap-2 rounded-full bg-club px-5 py-2.5 font-semibold text-ivory hover:bg-club-dark"><x-icon name="leagues" class="size-4" /> Start a GoalLeague</a>
                            <a href="{{ route('challenges.create') }}" class="flex items-center justify-center gap-2 rounded-full bg-gold px-5 py-2.5 font-semibold text-ink hover:brightness-105"><x-icon name="flag" class="size-4" /> Start a challenge</a>
                            <a href="{{ route('leagues.index') }}#join" class="rounded-full border border-club/30 px-5 py-2.5 text-center font-semibold text-club hover:border-club">Join with a code</a>
                        </div>
                    </div>
                @endforelse
            </section>

            {{-- Highlights. --}}
            <section>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="eyebrow text-club">Around the League</h2>
                    <a href="{{ route('highlights.index') }}" class="text-sm font-semibold text-club hover:underline">Feed</a>
                </div>
                <div class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @forelse ($highlights as $highlight)
                        <a href="{{ route('highlights.index') }}#highlight-{{ $highlight->id }}" class="flex items-start gap-3 px-4 py-3 hover:bg-mint/40">
                            <x-avatar :user="$highlight->user" class="size-8 text-[0.6rem]" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm"><span class="font-semibold text-ink">{{ Str::before($highlight->user->name, ' ') }}</span> <span class="text-ink/55">&middot; {{ $highlight->kind->label() }}</span></span>
                                <span class="block truncate text-sm text-ink/70">{{ $highlight->headline }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-3 text-sm text-ink/60">Follow friends to see their wins here. <a href="{{ route('people.index') }}" class="font-semibold text-club hover:underline">Find people</a></p>
                    @endforelse
                </div>
            </section>

            <x-scoring-explainer />

            @if ($retired->isNotEmpty())
                <section>
                    <h2 class="eyebrow text-ink/50">Retired goals</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach ($retired as $goal)
                            <li>
                                <a href="{{ route('goals.show', $goal) }}" class="text-sm text-ink/60 hover:text-club hover:underline">{{ $goal->title }}</a>
                                <span class="text-sm text-ink/40">&middot; retired {{ App\Clock::local($goal->retired_at)?->format('M j, Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>

{{-- Clubhouse > History: finished seasons, past goals and past GoalWeeks, newest first. Read-only, calm, and every
     number comes from the scoring code. --}}
<x-layouts.app title="History · GoalLine">
    <x-slot:hero>
        <x-page-header title="Your history" eyebrow="Clubhouse" subtitle="Every season, goal and GoalWeek you've played." />
    </x-slot:hero>

    @include('partials.clubhouse-tabs', ['active' => 'history'])

    <div class="space-y-5" data-first-content data-history>
        @include('finale.partials.preview-button')

        {{-- Goal Day check-ins still waiting on an answer. --}}
        @if ($goalDayPending->isNotEmpty())
            <section aria-labelledby="history-goal-day">
                <h2 id="history-goal-day" class="eyebrow mb-1.5 text-club">Goal Day</h2>
                <ul class="space-y-2">
                    @foreach ($goalDayPending as $pendingGoal)
                        <li>
                            <a href="{{ route('goal-day.show', $pendingGoal) }}" class="flex items-center justify-between gap-3 rounded-xl bg-gold px-3 py-2.5 text-ink hover:brightness-105" data-history-goal-day>
                                <span class="min-w-0 truncate text-[15px] font-bold">Did you reach "{{ $pendingGoal->title }}"?</span>
                                <span class="shrink-0 text-[13px] font-semibold">Check in &rarr;</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if ($goalDayPending->isEmpty() && $leagues->isEmpty() && $pastGoals->isEmpty() && $weeks->isEmpty())
            <x-empty-state title="Your history starts here"
                message="Finished GoalWeeks, seasons and goals land here. One more day on the line and you're building it."
                action="Back to Now" :href="route('dashboard')" />
        @endif

        {{-- Finished GoalLeagues, with their season summaries. --}}
        @if ($leagues->isNotEmpty())
            <section aria-labelledby="history-seasons">
                <h2 id="history-seasons" class="eyebrow mb-1.5 text-club">Seasons</h2>
                <ul class="space-y-2">
                    @foreach ($leagues as $row)
                        @php($league = $row['league'])
                        <li class="rounded-xl border border-line bg-white px-3 py-2.5" data-history-league>
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ route('leagues.show', $league) }}" class="min-w-0">
                                    <span class="block truncate text-[15px] font-bold text-club hover:underline">{{ $league->name }}</span>
                                    <span class="block text-xs text-ink/70">{{ $league->starts_on->format('M j') }}&ndash;{{ $league->ends_on->format('M j, Y') }} &middot; {{ $row['record']['wins'] }}&ndash;{{ $row['record']['ties'] }}&ndash;{{ $row['record']['losses'] }}</span>
                                </a>
                                <span class="shrink-0 text-right">
                                    <span class="block text-[17px] leading-tight font-bold text-ink tabular-nums">{{ $row['ordinal'] }}</span>
                                    <span class="block text-[11px] text-ink/70">of {{ $row['size'] }}</span>
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-[13px]">
                                @if ($row['achieved'])
                                    <span class="eyebrow rounded-full bg-gold px-2 py-0.5 text-[11px] text-ink">Promoted &middot; goal achieved</span>
                                @else
                                    <span class="text-ink/75">{{ $row['goal'] ? '“'.$row['goal']->title.'” is still ahead of you.' : 'A full season played.' }}</span>
                                    <a href="{{ route('leagues.create', array_filter(['goal' => $row['goal']?->isActive() ? $row['goal']->id : null])) }}"
                                        class="inline-flex min-h-9 items-center rounded-full border border-club/30 px-3 font-semibold text-club hover:border-club">Run it back</a>
                                @endif
                                @if ($row['goal'])
                                    <a href="{{ route('finale.show', ['goal' => $row['goal'], 'skip' => 1]) }}" class="inline-flex min-h-9 items-center rounded-full border border-club/30 px-3 font-semibold text-club hover:border-club" data-replay-finale>Season finale</a>
                                @endif
                                <a href="{{ route('leagues.recap', $league) }}" class="ml-auto inline-flex min-h-9 items-center font-semibold text-club hover:underline">Season recap &rarr;</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Goals that are done, retired, or past their date. --}}
        @if ($pastGoals->isNotEmpty())
            <section aria-labelledby="history-goals">
                <h2 id="history-goals" class="eyebrow mb-1.5 text-club">Goals</h2>
                <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                    @foreach ($pastGoals as $row)
                        <li class="px-3 py-2.5" data-history-goal>
                            <div class="flex items-center justify-between gap-3">
                                <a href="{{ route('goals.show', $row['goal']) }}" class="min-w-0">
                                    <span class="block truncate text-[15px] font-semibold text-ink hover:text-club hover:underline">{{ $row['goal']->title }}</span>
                                    <span class="block text-xs text-ink/70">{{ $row['label'] }} &middot; {{ $row['on']?->format('M j, Y') }} &middot; {{ $row['goal']->points() }} {{ Str::plural('star', $row['goal']->points()) }}</span>
                                </a>
                                <span @class([
                                    'eyebrow shrink-0 rounded-full px-2 py-0.5 text-[11px]',
                                    'bg-gold text-ink' => $row['state'] === 'completed',
                                    'bg-ivory text-ink/70 ring-1 ring-line ring-inset' => $row['state'] === 'retired',
                                    'bg-mint text-club' => $row['state'] === 'ended',
                                ])>{{ $row['state'] === 'completed' ? 'Done' : ($row['state'] === 'retired' ? 'Retired' : 'Date reached') }}</span>
                            </div>
                            @if ($row['goal']->result || $row['state'] === 'completed')
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[13px]">
                                    @if ($row['goal']->result)
                                        <span class="text-ink/75">{{ $row['goal']->result->outcome->label() }}{{ $row['goal']->result->isVerified() ? ' · Verified' : '' }}</span>
                                    @endif
                                    <a href="{{ route('finale.show', ['goal' => $row['goal'], 'skip' => 1]) }}" class="inline-flex min-h-9 items-center rounded-full border border-club/30 px-3 font-semibold text-club hover:border-club" data-replay-finale>Season finale</a>
                                </div>
                            @endif
                            @if ($row['state'] === 'ended' && ! $row['goal']->result)
                                <div class="mt-2 flex flex-wrap gap-2 text-[13px]">
                                    <a href="{{ route('goal-day.show', $row['goal']) }}" class="inline-flex min-h-9 items-center rounded-full bg-club px-3 font-semibold text-ivory hover:bg-club-dark">Goal Day check-in</a>
                                    <a href="{{ route('goals.edit', $row['goal']) }}" class="inline-flex min-h-9 items-center rounded-full border border-club/30 px-3 font-semibold text-club hover:border-club">Set a new date</a>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Finished GoalWeeks. --}}
        @if ($weeks->isNotEmpty())
            <section aria-labelledby="history-weeks">
                <h2 id="history-weeks" class="eyebrow mb-1.5 text-club">Past GoalWeeks</h2>
                <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                    @foreach ($weeks as $row)
                        @php($week = $row['week'])
                        <li data-history-week>
                            <a href="{{ route('history.weeks.show', $week) }}" class="flex items-center gap-3 px-3 py-2.5 hover:bg-mint/40">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[14px] font-semibold text-ink">{{ $week->goal->title }}</span>
                                    <span class="block truncate text-xs text-ink/70">
                                        {{ $week->label() }} &middot; {{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format('M j') }}
                                        @if ($row['match'])
                                            &middot; {{ App\Services\History::matchLabel($row['match']) }}
                                        @endif
                                    </span>
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-0.5">
                                    @if ($row['result'])
                                        <x-goalweek-result :result="$row['result']" compact />
                                    @endif
                                    <span class="text-xs text-ink/75 tabular-nums">{{ $row['percent'] }}% &middot; <span class="font-semibold text-ink">{{ $row['weeklyPoints'] }} {{ Str::plural('star', $row['weeklyPoints']) }}</span></span>
                                </span>
                                <x-icon name="chevron-right" class="size-4 shrink-0 text-ink/50" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($weeks->count() >= App\Services\History::WEEKS_SHOWN)
                    <p class="mt-1.5 text-xs text-ink/70">Showing your latest {{ App\Services\History::WEEKS_SHOWN }} GoalWeeks. Each goal's page has every week.</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>

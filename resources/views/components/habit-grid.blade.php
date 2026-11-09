{{-- This week's habits as a grid: days across, habits down. Today and yesterday can be logged with one tap (no
     backfilling further); other days show what happened. States: done, partial (a number under the rule), due,
     rest day, upcoming. Missed days stay neutral, never red. --}}
@props(['gameplan'])

@php
    $today = $gameplan->today;
    $yesterday = $today->copy()->subDay();
    $monday = $today->copy()->startOfWeek(Carbon\CarbonInterface::MONDAY);
    $days = collect(range(0, 6))->map(fn (int $offset) => $monday->copy()->addDays($offset));
    $rows = $gameplan->entries->flatMap(fn (array $entry) => $entry['week']->habits()
        ->filter(fn ($habit) => $habit->archived_at === null)
        ->map(fn ($habit) => ['habit' => $habit, 'week' => $entry['week'], 'goal' => $entry['goal']]));
@endphp

<section id="week-grid" data-region="week-grid" {{ $attributes->class('scroll-mt-24 rounded-2xl border border-line bg-white') }}>
    <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-4">
        <h2 class="font-serif text-xl font-bold text-club tracking-tight">This week&rsquo;s habits</h2>
        <p class="text-xs text-ink/70">Tap today or yesterday to log. Earlier days stay as they are.</p>
    </header>

    @if ($rows->isEmpty())
        <p class="px-5 py-6 text-sm text-ink/65">No habits yet. Add the recurring things that move your goal, like &ldquo;Run 2+ miles&rdquo; 3 days a week, from your goal page.</p>
    @else
        <div class="relative overflow-x-auto" tabindex="0" role="region" aria-label="This week's habits, scrolls sideways">
            <table class="w-full min-w-[34rem] text-sm">
                <thead>
                    <tr class="text-xs text-ink/70">
                        <th class="px-4 py-2 text-left font-semibold">Habit</th>
                        @foreach ($days as $day)
                            <th @class(['px-1 py-2 text-center font-semibold', 'text-club' => $day->isSameDay($today)])>
                                {{ $day->format('D') }}<span class="block font-normal text-ink/70">{{ $day->format('j') }}</span>
                            </th>
                        @endforeach
                        <th class="px-4 py-2 text-right font-semibold">Week</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as ['habit' => $habit, 'week' => $week, 'goal' => $goal])
                        @php
                            $done = $habit->doneIn($week);
                            $target = $habit->targetFor($week);
                        @endphp
                        <tr>
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-ink">{{ $habit->label() }}</p>
                                <p class="text-xs text-ink/70">{{ $habit->cadence() }} &middot; <a href="{{ route('goals.show', $goal) }}#habits" class="hover:text-club hover:underline">{{ Str::limit($goal->title, 28) }}</a></p>
                            </td>
                            @foreach ($days as $day)
                                @php
                                    $inWeek = $day->between($week->starts_on, $week->ends_on) || $day->isSameDay($yesterday);
                                    $loggable = $inWeek && ($day->isSameDay($today) || $day->isSameDay($yesterday));
                                    $isDone = $habit->isDoneOn($day);
                                    $partial = $habit->isPartialOn($day);
                                @endphp
                                <td class="px-1 py-2 text-center">
                                    <div class="flex justify-center">
                                        @if ($loggable)
                                            <x-habit-log :habit="$habit" :day="$day" return="today" compact />
                                        @elseif ($isDone)
                                            <span class="grid size-9 place-items-center rounded-lg bg-grass text-ink" title="Done">
                                                <svg viewBox="0 0 16 16" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                                                <span class="sr-only">Done</span>
                                            </span>
                                        @elseif ($partial)
                                            <span class="grid size-9 place-items-center rounded-lg bg-gold/15 text-xs font-semibold text-ink tabular-nums ring-1 ring-gold" title="Logged, under the rule">{{ $habit->formatNumber($habit->logOn($day)->value ?? 0) }}</span>
                                        @elseif ($day->gt($today))
                                            <span class="grid size-9 place-items-center rounded-lg text-xs text-ink/30 border border-dashed border-line" title="Upcoming">{{ $habit->isRestDay($day) ? 'Rest' : '' }}<span class="sr-only">Upcoming</span></span>
                                        @elseif ($habit->isRestDay($day))
                                            <span class="grid size-9 place-items-center rounded-lg text-[0.65rem] text-ink/70" title="Rest day">Rest</span>
                                        @else
                                            <span class="grid size-9 place-items-center rounded-lg bg-ivory ring-1 ring-line" title="Not logged"><span class="sr-only">Not logged</span></span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-right">
                                <p @class(['font-semibold tabular-nums', 'text-club' => $done >= $target, 'text-ink' => $done < $target])>{{ $done }} of {{ $target }}</p>
                                <p class="text-xs text-ink/70">{{ $done >= $target ? 'Target met' : 'this week' }}</p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-line px-5 py-3 text-xs text-ink/70">
            <span class="mr-3 inline-flex items-center gap-1"><span class="size-3 rounded bg-grass"></span> Done</span>
            <span class="mr-3 inline-flex items-center gap-1"><span class="size-3 rounded bg-gold/15 ring-1 ring-gold"></span> Logged, under the rule</span>
            <span class="mr-3 inline-flex items-center gap-1"><span class="size-3 rounded ring-1 ring-line"></span> Due or not logged</span>
            <span class="inline-flex items-center gap-1">Rest: not a scheduled day</span>
        </p>
    @endif
</section>

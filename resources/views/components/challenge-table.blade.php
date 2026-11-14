{{-- The Challenge table, in Challenge gold: Rank, Player (sticky), Days (the ranking column: days checked in out of the
     target), Run, Best run, % of target, Last 5 days and Finished. It swipes sideways in its own box, a heading sorts
     by its column in place (?col= without scripts), and "Reset to official order" goes back to days, then the current
     run. Same behaviour as the GoalLeague table (data-standings-table in app.js). --}}
@props(['challenge', 'rows', 'me', 'column' => null])

@php
    $official = 'Official order: most days checked in, then the longest current run.';
    $columns = [
        'days' => ['label' => 'Days', 'title' => 'Days checked in, out of the '.$challenge->target_days.' needed to finish. The table is ranked by this.'],
        'run' => ['label' => 'Run', 'title' => 'Days in a row right now.'],
        'bestRun' => ['label' => 'Best run', 'title' => 'Longest run of days in a row.'],
        'percent' => ['label' => '% done', 'title' => 'Share of the target reached.'],
        'last' => ['label' => 'Last 5 days', 'title' => 'The last five days: gold is a check-in.'],
        'finished' => ['label' => 'Finished', 'title' => 'Reached the target.'],
    ];
    $sortable = ['days', 'run', 'bestRun', 'percent'];
    if ($column) {
        $rows = $rows->sort(fn ($a, $b) => [$b[$column], $a['rank']] <=> [$a[$column], $b['rank']])->values();
    }
    $note = $column ? 'Sorted by '.$columns[$column]['label'].', highest first. '.$columns[$column]['title'] : $official;
@endphp

<div {{ $attributes->class('min-w-0') }} data-standings-table data-challenge-table data-official-note="{{ $official }}">
    <div class="mb-1.5 flex min-h-6 items-center justify-between gap-2">
        <p class="min-w-0 text-[13px] text-ink/75" data-sort-note aria-live="polite">{{ $note }}</p>
        <span class="hidden shrink-0 items-center gap-1 rounded-full bg-gold-light px-2.5 py-1 text-[13px] font-semibold text-ink" data-swipe-hint aria-hidden="true">Swipe for more <span>&rarr;</span></span>
    </div>

    <div class="relative">
        <div class="gl-table-scroll overflow-x-auto overscroll-x-contain rounded-xl border border-gold/60 bg-white" data-table-scroll tabindex="0" role="region" aria-label="Challenge table. It scrolls sideways for more columns.">
            <table class="w-max min-w-full border-separate border-spacing-0 text-left text-[13px] tabular-nums">
                <caption class="sr-only">Leaderboard for {{ $challenge->title }}. {{ $official }}</caption>
                <thead class="text-[13px] text-ink">
                    <tr>
                        <th scope="col" class="sticky left-0 z-20 w-9 bg-gold py-2 pl-3 text-left font-semibold">#</th>
                        <th scope="col" class="gl-sticky-edge sticky left-9 z-20 w-[7.25rem] bg-gold py-2 pr-2 pl-1 text-left font-semibold">Player</th>
                        @foreach ($columns as $key => $col)
                            <th scope="col" data-col="{{ $key }}" title="{{ $col['title'] }}" @class(['snap-start bg-gold px-0.5 py-1 font-semibold whitespace-nowrap', 'text-left' => $key === 'last', 'text-center' => $key !== 'last'])
                                @if ($column === $key) aria-sort="descending" @endif>
                                @if (in_array($key, $sortable, true))
                                    <a href="{{ route('challenges.show', [$challenge, 'col' => $key]) }}" data-sort-col="{{ $key }}" data-title="{{ $col['title'] }}" data-label="{{ $col['label'] }}"
                                        class="inline-flex min-h-11 items-center justify-center gap-0.5 rounded-md px-1 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-ink">
                                        <span @class(['font-extrabold' => $key === 'days'])>{{ $col['label'] }}</span>
                                        <span data-sort-arrow aria-hidden="true" class="{{ $column === $key ? '' : 'hidden' }}">&darr;</span>
                                    </a>
                                @else
                                    <span class="inline-flex min-h-11 items-center px-1.5">{{ $col['label'] }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $user = $row['member']->user;
                            $mine = $user->is($me);
                            $cellBg = $mine ? 'bg-[#fbf1d6]' : 'bg-white group-hover:bg-ivory';
                            $lastLabel = 'Last 5 days: '.(collect($row['lastDays'])->map(fn ($day) => $day['done'] ? 'checked in' : 'missed')->implode(', ') ?: 'not started');
                        @endphp
                        <tr class="group cursor-pointer" data-standings-row="{{ $row['member']->id }}" data-rank="{{ $row['rank'] }}">
                            <td @class(['sticky left-0 z-10 border-t border-line py-0 pl-3 font-semibold', $cellBg, 'text-gold-ink' => $row['rank'] === 1 && $row['days'] > 0, 'text-ink/70' => $row['rank'] !== 1 || $row['days'] === 0])>{{ $row['rank'] }}</td>
                            <th scope="row" class="gl-sticky-edge sticky left-9 z-10 w-[7.25rem] max-w-[7.25rem] border-t border-line py-0 pr-2 pl-1 font-normal {{ $cellBg }}">
                                <a href="{{ $user->profileUrl() }}" data-row-link class="flex min-h-11 items-center gap-2">
                                    <x-avatar :user="$user" class="size-7 shrink-0 text-[11px]" />
                                    <span class="min-w-0 truncate font-semibold text-ink group-hover:text-gold-ink">{{ $user->name }}<span class="sr-only">{{ $mine ? ' (you)' : '' }}, place {{ $row['rank'] }}: see their profile</span></span>
                                </a>
                            </th>
                            <td data-cell="days" data-value="{{ $row['days'] }}" class="border-t border-line px-1.5 py-0 text-center {{ $cellBg }}"><span class="text-[16px] font-extrabold text-ink">{{ $row['days'] }}</span><span class="text-ink/70">/{{ $challenge->target_days }}</span></td>
                            <td data-cell="run" data-value="{{ $row['run'] }}" class="border-t border-line px-1.5 py-0 text-center text-ink {{ $cellBg }}">{{ $row['run'] }}</td>
                            <td data-cell="bestRun" data-value="{{ $row['bestRun'] }}" class="border-t border-line px-1.5 py-0 text-center text-ink {{ $cellBg }}">{{ $row['bestRun'] }}</td>
                            <td data-cell="percent" data-value="{{ $row['percent'] }}" class="border-t border-line px-1.5 py-0 text-center text-ink {{ $cellBg }}">{{ $row['percent'] }}%</td>
                            <td data-cell="last" class="border-t border-line px-1.5 py-0 {{ $cellBg }}">
                                <span class="flex min-h-11 items-center gap-1" role="img" aria-label="{{ $lastLabel }}">
                                    @forelse ($row['lastDays'] as $day)
                                        <span @class(['grid size-[18px] place-items-center rounded-full text-[11px] leading-none font-bold', 'bg-gold text-ink' => $day['done'], 'bg-white text-ink/60 ring-1 ring-line ring-inset' => ! $day['done']]) data-day="{{ $day['date'] }}">{{ $day['done'] ? '✓' : '–' }}</span>
                                    @empty
                                        <span class="text-ink/60">Not started</span>
                                    @endforelse
                                </span>
                            </td>
                            <td data-cell="finished" class="border-t border-line px-1.5 py-0 text-center {{ $cellBg }}">
                                @if ($row['finished'])
                                    <x-badge :badge="App\Badge::ChallengeFinisher" class="mx-auto size-7" />
                                @else
                                    <span class="text-ink/60">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <span class="pointer-events-none absolute inset-y-px right-px w-6 rounded-r-xl bg-gradient-to-l from-ink/10 to-transparent transition-opacity" data-scroll-edge aria-hidden="true"></span>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <details class="text-[13px] text-ink/75">
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-gold-ink">What the columns mean</summary>
            <dl class="mt-1 grid gap-1 pb-2">
                @foreach ($columns as $col)
                    <div class="flex gap-2"><dt class="w-20 shrink-0 font-semibold text-ink">{{ $col['label'] }}</dt><dd>{{ $col['title'] }}</dd></div>
                @endforeach
            </dl>
        </details>
        <a href="{{ route('challenges.show', $challenge) }}" data-reset-order @if (! $column) hidden @endif @class(['inline-flex min-h-11 items-center rounded-full px-3 text-[13px] font-semibold text-gold-ink ring-1 ring-gold ring-inset hover:bg-gold-light/40'])>Reset to official order</a>
    </div>
</div>

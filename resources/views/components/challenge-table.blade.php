{{-- The Challenge Standings, in Challenge gold, on the same swipeable table as a GoalLeague's (data-standings-table in
     app.js): Rank, Member (sticky), Completed, the record ("38 of 40 days"), Completion %, Current streak, Best streak,
     Restarts (strict) or Misses used (flexible) and Last 5; swipe for Verified check-ins and when they joined. A heading
     sorts in place (?col= without scripts); "Reset to official order" goes back to completion %, then the current
     streak, then the completed count. A row opens that member's days in a sheet. Numbers from ChallengeProgress. --}}
@props(['challenge', 'rows', 'me', 'column' => null])

@php
    $unit = $challenge->isWeekly() ? 'week' : 'day';
    $official = 'Official order: Completion %, then current streak, then '.Str::plural($unit).' completed.';
    $columns = [
        'completed' => ['label' => 'Done', 'title' => Str::ucfirst(Str::plural($unit)).' completed so far.'],
        'record' => ['label' => 'Record', 'title' => Str::ucfirst(Str::plural($unit)).' completed out of all the required '.Str::plural($unit).'.'],
        'percent' => ['label' => 'Completion', 'title' => 'Share of the required '.Str::plural($unit).' completed. The table is ranked by this.'],
        'streak' => ['label' => 'Streak', 'title' => 'Current streak, in '.Str::plural($unit).'.'],
        'bestStreak' => ['label' => 'Best', 'title' => 'Best streak, in '.Str::plural($unit).'.'],
        'misses' => $challenge->isStrict()
            ? ['label' => 'Restarts', 'title' => 'Times a missed '.$unit.' restarted the streak (strict mode).']
            : ['label' => 'Misses used', 'title' => 'Allowed misses used, of '.$challenge->allowed_misses.' (flexible mode).'],
        'last' => ['label' => 'Last 5', 'title' => 'The last five '.Str::plural($unit).': gold is done, a dash missed, R rest, S slipped, a dot a forgiven miss.'],
        'verified' => ['label' => 'Verified', 'title' => 'Check-ins with a same-day proof photo.'],
        'joined' => ['label' => 'Joined', 'title' => 'When they joined.'],
    ];
    $sortable = ['completed', 'percent', 'streak', 'bestStreak', 'misses', 'verified'];
    $value = fn (array $row, string $key) => match ($key) {
        'misses' => $challenge->isStrict() ? $row['restarts'] : $row['missesUsed'],
        default => $row[$key] ?? 0,
    };
    if ($column) {
        $rows = $rows->sort(fn ($a, $b) => [$value($b, $column), $a['rank']] <=> [$value($a, $column), $b['rank']])->values();
    }
    $note = $column ? 'Sorted by '.$columns[$column]['label'].', highest first. '.$columns[$column]['title'] : $official;
    $circle = fn (array $period) => match (true) {
        $period['forgiven'] => ['bg-ivory text-ink/70 ring-1 ring-ink/30', '·', 'forgiven miss'],
        $period['status'] === 'done' => ['bg-gold text-ink', '✓', 'done'],
        $period['status'] === 'rest' => ['bg-ivory text-ink/70 ring-1 ring-line', 'R', 'rest'],
        $period['status'] === 'slipped' => ['bg-red-100 text-red-800', 'S', 'slipped'],
        default => ['bg-white text-ink/70 ring-1 ring-inset ring-line', '–', 'missed'],
    };
@endphp

<div {{ $attributes->class('min-w-0') }} data-standings-table data-challenge-table data-official-note="{{ $official }}">
    <div class="mb-1.5 flex min-h-6 items-center justify-between gap-2">
        <p class="min-w-0 text-[13px] text-ink/75" data-sort-note aria-live="polite">{{ $challenge->hasStarted() ? $note : 'Kickoff '.$challenge->starts_on->format('l, M j').'. Everyone starts level.' }}</p>
        <span class="hidden shrink-0 items-center gap-1 rounded-full bg-gold-light px-2.5 py-1 text-[13px] font-semibold text-ink" data-swipe-hint aria-hidden="true">Swipe for more <span>&rarr;</span></span>
    </div>

    <div class="relative">
        <div class="gl-table-scroll relative overflow-x-auto overscroll-x-contain rounded-xl border border-gold/60 bg-white" data-table-scroll tabindex="0" role="region" aria-label="Challenge Standings. It scrolls sideways for more columns.">
            <table class="w-max min-w-full border-separate border-spacing-0 text-left text-[13px] tabular-nums">
                <caption class="sr-only">Standings for {{ $challenge->title }}. {{ $official }}</caption>
                <thead class="text-[13px] text-ink">
                    <tr>
                        <th scope="col" class="sticky left-0 z-20 w-9 bg-gold py-2 pl-3 text-left font-semibold" title="Place in the official order">#</th>
                        <th scope="col" class="gl-sticky-edge sticky left-9 z-20 w-[7.25rem] bg-gold py-2 pr-2 pl-1 text-left font-semibold">Member</th>
                        @foreach ($columns as $key => $col)
                            <th scope="col" data-col="{{ $key }}" title="{{ $col['title'] }}" @class(['snap-start bg-gold px-0.5 py-1 font-semibold whitespace-nowrap', 'text-left' => $key === 'last', 'text-center' => $key !== 'last'])
                                @if ($column === $key) aria-sort="descending" @endif>
                                @if (in_array($key, $sortable, true) && $challenge->hasStarted())
                                    <a href="{{ route('challenges.show', [$challenge, 'col' => $key]) }}" data-sort-col="{{ $key }}" data-title="{{ $col['title'] }}" data-label="{{ $col['label'] }}"
                                        class="inline-flex min-h-11 items-center justify-center gap-0.5 rounded-md px-1 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-ink">
                                        <span @class(['font-extrabold' => $key === 'percent'])>{{ $col['label'] }}</span>
                                        <span data-sort-arrow aria-hidden="true" class="{{ $column === $key ? '' : 'hidden' }}">&darr;</span>
                                    </a>
                                @else
                                    <span class="inline-flex min-h-11 items-center px-1">{{ $col['label'] }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $member = $row['member'];
                            $user = $member->user;
                            $mine = $user->is($me);
                            $cellBg = $mine ? 'bg-[#fbf1d6]' : 'bg-white group-hover:bg-ivory';
                            $cell = 'border-t border-line px-1.5 py-0 '.$cellBg;
                        @endphp
                        <tr class="group cursor-pointer" data-standings-row="{{ $member->id }}" data-rank="{{ $row['rank'] }}">
                            <td @class(['sticky left-0 z-10 py-0 pl-3 font-semibold', $cell, 'text-gold-ink' => $row['rank'] === 1 && $row['completed'] > 0, 'text-ink/70' => $row['rank'] !== 1 || $row['completed'] === 0])>{{ $row['rank'] }}</td>
                            <th scope="row" class="gl-sticky-edge sticky left-9 z-10 w-[7.25rem] max-w-[7.25rem] py-0 pr-2 pl-1 font-normal {{ $cell }}">
                                <a href="{{ route('challenges.members.show', [$challenge, $member]) }}" data-task-sheet-open data-row-link class="flex min-h-11 items-center gap-2">
                                    <x-avatar :user="$user" class="size-7 shrink-0 text-[11px]" />
                                    <span class="min-w-0 truncate font-semibold text-ink group-hover:text-gold-ink">{{ $user->name }}<span class="sr-only">{{ $mine ? ' (you)' : '' }}, place {{ $row['rank'] }}: see their {{ Str::plural($unit) }}</span></span>
                                </a>
                            </th>
                            <td data-cell="completed" data-value="{{ $row['completed'] }}" class="{{ $cell }} text-center"><span class="text-[16px] font-extrabold text-ink">{{ $row['completed'] }}</span></td>
                            <td data-cell="record" class="{{ $cell }} text-center whitespace-nowrap text-ink">{{ $row['completed'] }} of {{ $row['total'] }} {{ Str::plural($unit, $row['total']) }}</td>
                            <td data-cell="percent" data-value="{{ $row['percent'] }}" class="{{ $cell }} text-center font-bold text-ink">{{ $row['percent'] }}%</td>
                            <td data-cell="streak" data-value="{{ $row['streak'] }}" class="{{ $cell }} text-center text-ink">{{ $row['streak'] }}</td>
                            <td data-cell="bestStreak" data-value="{{ $row['bestStreak'] }}" class="{{ $cell }} text-center text-ink">{{ $row['bestStreak'] }}</td>
                            <td data-cell="misses" data-value="{{ $value($row, 'misses') }}" class="{{ $cell }} text-center text-ink">{{ $challenge->isStrict() ? $row['restarts'] : $row['missesUsed'].' of '.$challenge->allowed_misses }}</td>
                            <td data-cell="last" class="{{ $cell }}">
                                <span class="flex min-h-11 items-center gap-1">
                                    <span class="sr-only">Last 5: {{ collect($row['last5'])->map(fn ($period) => $circle($period)[2])->implode(', ') ?: 'nothing yet' }}.</span>
                                    @forelse ($row['last5'] as $period)
                                        @php [$classes, $letter] = $circle($period); @endphp
                                        <span class="grid size-[18px] place-items-center rounded-full text-[11px] leading-none font-bold {{ $classes }}" data-period-status="{{ $period['forgiven'] ? 'forgiven' : $period['status'] }}" title="{{ $period['label'] }}" aria-hidden="true">{{ $letter }}</span>
                                    @empty
                                        <span class="text-ink/70">Not started</span>
                                    @endforelse
                                </span>
                            </td>
                            <td data-cell="verified" data-value="{{ $row['verified'] }}" class="{{ $cell }} text-center text-ink">{{ $row['verified'] }}</td>
                            <td data-cell="joined" class="{{ $cell }} text-center whitespace-nowrap text-ink/80">{{ $member->created_at?->format('M j') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <span class="pointer-events-none absolute inset-y-px right-px w-6 rounded-r-xl bg-gradient-to-l from-ink/10 to-transparent transition-opacity" data-scroll-edge aria-hidden="true"></span>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <details class="text-[13px] text-ink/75" data-challenge-ranking>
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-gold-ink">How Challenges are ranked</summary>
            <div class="pb-2">
                <p class="mt-1">By completion: the share of the required {{ Str::plural($unit) }} done. Ties go to the longer current streak, then more {{ Str::plural($unit) }} completed. Challenges never earn stars and never change GoalLeague Standings.</p>
                <dl class="mt-2 grid gap-1">
                    @foreach ($columns as $col)
                        <div class="flex gap-2"><dt class="w-24 shrink-0 font-semibold text-ink">{{ $col['label'] }}</dt><dd>{{ $col['title'] }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </details>
        <a href="{{ route('challenges.show', $challenge) }}" data-reset-order @if (! $column) hidden @endif class="inline-flex min-h-11 items-center rounded-full px-3 text-[13px] font-semibold text-gold-ink ring-1 ring-gold ring-inset hover:bg-gold-light/40">Reset to official order</a>
    </div>

    {{-- A member's days, filled when a row is tapped. --}}
    <dialog data-task-sheet aria-labelledby="challenge-member-title" class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-3xl">
        <div class="flex justify-between gap-3 px-5 pt-3">
            <span class="mx-auto mt-1 h-1.5 w-10 rounded-full bg-ink/15 sm:hidden" aria-hidden="true"></span>
        </div>
        <form method="dialog" class="absolute top-3 right-3 z-10">
            <button type="submit" class="grid size-11 place-items-center rounded-full bg-white/80 text-ink/70 hover:bg-ivory" aria-label="Close">&times;</button>
        </form>
        <div data-task-sheet-body class="max-h-[85dvh] overflow-y-auto px-4 pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))]"></div>
    </dialog>
</div>

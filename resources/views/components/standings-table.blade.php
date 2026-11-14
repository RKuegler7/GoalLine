{{-- The GoalLeague table: Rank, Player (sticky), Stars (the ranking column), Record, Line ★, Matchup ★, Best week and
     Last 5, then more columns that swipe in from the right inside the table's own box (the page never scrolls
     sideways). A heading sorts by its column in place (or ?col= without scripts) and says what the column means;
     "Reset to official order" goes back to total stars, then Line ★, then Past-the-Line weeks. A row opens that
     player's season in a sheet. Every number comes from LeagueTable and LeagueStats. --}}
@props(['rows', 'league', 'me' => null, 'column' => null])

@php
    $columns = App\Services\LeagueStats::columns();
    $forming = $league->status() === App\LeagueStatus::Forming;
    $official = 'Official order: total Stars, then Line ★, then Past-the-Line weeks.';
    $circle = fn ($band) => match ($band) {
        App\GoalWeekResult::Smashed => 'bg-sky-700 text-white',
        App\GoalWeekResult::Past => 'bg-gold text-ink',
        App\GoalWeekResult::InLine => 'bg-grass text-ink',
        default => 'bg-red-100 text-red-800',
    };
    $note = $column ? 'Sorted by '.$columns[$column]['label'].', highest first. '.$columns[$column]['title'] : $official;
    $playerUrl = fn ($member) => route('leagues.standings.player', [$league, $member]);
@endphp

<div {{ $attributes->class('min-w-0') }} data-standings-table data-official-note="{{ $official }}">
    <div class="mb-1.5 flex min-h-6 items-center justify-between gap-2">
        <p class="min-w-0 text-[13px] text-ink/75" data-sort-note aria-live="polite">{{ $forming ? 'Season starts '.$league->starts_on->format('l, M j').'. Everyone starts level.' : $note }}</p>
        <span class="hidden shrink-0 items-center gap-1 rounded-full bg-mint px-2.5 py-1 text-[13px] font-semibold text-club" data-swipe-hint aria-hidden="true">Swipe for more <span>&rarr;</span></span>
    </div>

    <div class="relative">
        <div class="gl-table-scroll overflow-x-auto overscroll-x-contain rounded-xl border border-line bg-white" data-standings data-table-scroll tabindex="0" role="region" aria-label="Standings table. It scrolls sideways for more columns.">
            <table class="w-max min-w-full border-separate border-spacing-0 text-left text-[13px] tabular-nums">
                <caption class="sr-only">Standings for {{ $league->name }}. {{ $official }}</caption>
                <thead class="text-[13px] text-ivory">
                    <tr>
                        <th scope="col" class="sticky left-0 z-20 w-9 bg-club py-2 pl-3 text-left font-semibold" title="Place in the official order">#</th>
                        <th scope="col" class="gl-sticky-edge sticky left-9 z-20 w-[7.25rem] bg-club py-2 pr-2 pl-1 text-left font-semibold">Player</th>
                        @foreach ($columns as $key => $col)
                            <th scope="col" data-col="{{ $key }}" title="{{ $col['title'] }}" @class(['snap-start bg-club px-0.5 py-1 font-semibold whitespace-nowrap', 'text-left' => $key === 'form', 'text-center' => $key !== 'form'])
                                @if ($column === $key) aria-sort="descending" @endif>
                                @if ($col['value'] && ! $forming)
                                    <a href="{{ route('leagues.standings', [$league, 'col' => $key]) }}" data-sort-col="{{ $key }}" data-title="{{ $col['title'] }}" data-label="{{ $col['label'] }}"
                                        class="inline-flex min-h-11 items-center justify-center gap-0.5 rounded-md px-1 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-ivory">
                                        <span @class(['font-extrabold' => $key === 'total'])>{{ $col['label'] }}</span>
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
                            $member = $row['member'];
                            $mine = $me && $member->is($me);
                            $stats = $row['stats'];
                            $cellBg = $mine ? 'bg-mint' : 'bg-white group-hover:bg-ivory';
                        @endphp
                        <tr class="group cursor-pointer" data-standings-row="{{ $member->id }}" data-rank="{{ $row['rank'] }}">
                            <td class="sticky left-0 z-10 border-t border-line py-0 pl-3 font-semibold text-ink/70 {{ $cellBg }}">{{ $row['rank'] }}</td>
                            <th scope="row" class="gl-sticky-edge sticky left-9 z-10 w-[7.25rem] max-w-[7.25rem] border-t border-line py-0 pr-2 pl-1 font-normal {{ $cellBg }}">
                                <a href="{{ $playerUrl($member) }}" data-task-sheet-open data-row-link class="flex min-h-11 items-center gap-2">
                                    <x-avatar :user="$member->user" class="size-7 shrink-0 text-[11px]" />
                                    <span class="min-w-0 truncate font-semibold text-ink group-hover:text-club">{{ $member->displayName() }}<span class="sr-only">{{ $mine ? ' (you)' : '' }}, place {{ $row['rank'] }}: see their season</span></span>
                                </a>
                            </th>
                            @foreach ($columns as $key => $col)
                                <td @if ($col['value']) data-value="{{ $col['value']($row) }}" @endif data-cell="{{ $key }}"
                                    @class(['border-t border-line px-1.5 py-0 text-ink', $cellBg, 'text-center' => $key !== 'form'])>
                                    @if ($forming)
                                        <span class="text-ink/60">&mdash;</span>
                                    @else
                                        @switch($key)
                                            @case('total')
                                                <span class="inline-flex items-center gap-0.5 text-[16px] font-extrabold text-club">{{ $row['total'] }}<svg viewBox="0 0 20 20" class="size-3.5 text-gold" fill="currentColor" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1 5.9L10 14.9l-5.2 2.8 1-5.9L1.5 7.7l5.9-.8z" /></svg></span>
                                                @break
                                            @case('record')
                                                {{ $row['wins'] }}-{{ $row['ties'] }}-{{ $row['losses'] }}
                                                @break
                                            @case('best_week')
                                                {{ $stats['bestWeek'] ? $stats['bestWeek']['weeklyPoints'].' ★' : '—' }}
                                                @break
                                            @case('avg')
                                                {{ $stats['avgPercent'] !== null ? $stats['avgPercent'].'%' : '—' }}
                                                @break
                                            @case('plus_minus')
                                                {{ $stats['plusMinus'] > 0 ? '+'.$stats['plusMinus'] : ($stats['plusMinus'] < 0 ? '−'.abs($stats['plusMinus']) : '0') }}
                                                @break
                                            @case('form')
                                                <a href="{{ $playerUrl($member) }}#week-by-week" data-task-sheet-open class="flex min-h-11 items-center gap-1" data-form>
                                                    <span class="sr-only">{{ App\Services\LeagueStats::formLabel($stats['formWeeks']) }}. Open {{ $member->displayName() }}'s week-by-week list.</span>
                                                    @forelse ($stats['formWeeks'] as $week)
                                                        <span class="grid size-[18px] place-items-center rounded-full text-[11px] leading-none font-bold {{ $circle($week['band']) }}" data-form-week="{{ $week['week'] }}" data-letter="{{ $week['letter'] }}" aria-hidden="true">{{ $week['letter'] }}</span>
                                                    @empty
                                                        <span class="text-ink/60">No weeks yet</span>
                                                    @endforelse
                                                </a>
                                                @break
                                            @default
                                                {{ $col['value']($row) }}
                                        @endswitch
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- A soft edge on the right says there's more; it fades out at the end. --}}
        <span class="pointer-events-none absolute inset-y-px right-px w-6 rounded-r-xl bg-gradient-to-l from-ink/10 to-transparent transition-opacity" data-scroll-edge aria-hidden="true"></span>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <details class="text-[13px] text-ink/75" data-column-guide>
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-club">What the columns mean</summary>
            <dl class="mt-1 grid gap-1 pb-2">
                @foreach ($columns as $key => $col)
                    <div class="flex gap-2"><dt class="w-20 shrink-0 font-semibold text-ink">{{ $col['label'] }}</dt><dd>{{ $col['title'] }}</dd></div>
                @endforeach
            </dl>
        </details>
        <a href="{{ route('leagues.standings', $league) }}" data-reset-order @if (! $column) hidden @endif @class(['inline-flex min-h-11 items-center rounded-full px-3 text-[13px] font-semibold text-club ring-1 ring-club/30 ring-inset hover:bg-mint'])>Reset to official order</a>
    </div>

    {{-- The player's season, filled when a row is tapped (the same content as the player's page). --}}
    <dialog data-task-sheet aria-labelledby="player-sheet-title" class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-3xl">
        <div class="flex justify-between gap-3 px-5 pt-3">
            <span class="mx-auto mt-1 h-1.5 w-10 rounded-full bg-ink/15 sm:hidden" aria-hidden="true"></span>
        </div>
        <form method="dialog" class="absolute top-3 right-3 z-10">
            <button type="submit" class="grid size-11 place-items-center rounded-full bg-white/80 text-ink/70 hover:bg-ivory" aria-label="Close">&times;</button>
        </form>
        <div data-task-sheet-body class="max-h-[85dvh] overflow-y-auto px-4 pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))]"></div>
    </dialog>
</div>

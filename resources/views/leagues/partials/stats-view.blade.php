{{-- Stats: every member's numbers, from the same services as the table. Presets choose the columns, a column heading
     sorts by it (highest first), and "Reset to official order" goes back to total stars, then line stars, then
     Past-the-Line weeks. --}}
@php
    $columns = App\Services\LeagueStats::columns();
    $shown = App\Services\LeagueStats::PRESETS[$preset]['columns'];
    $forming = $league->status() === App\LeagueStatus::Forming;
    $chip = fn ($band) => match ($band) {
        App\GoalWeekResult::Smashed => ['bg-sky-700 text-white', 'S', 'Smashed'],
        App\GoalWeekResult::Past => ['bg-gold text-ink', 'P', 'Past the line'],
        App\GoalWeekResult::InLine => ['bg-grass text-ink', 'L', 'In line'],
        default => ['bg-red-100 text-red-800', '–', 'Short'],
    };
@endphp

<section aria-labelledby="stats-title" data-stats-view>
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 id="stats-title" class="eyebrow text-club">Stats</h2>
        <nav aria-label="Column presets" class="flex flex-wrap gap-1">
            @foreach (App\Services\LeagueStats::PRESETS as $key => $set)
                <a href="{{ route('leagues.standings', array_filter([$league, 'view' => 'stats', 'preset' => $key, 'col' => $column])) }}" @if ($preset === $key) aria-current="true" @endif
                    @class(['inline-flex min-h-11 items-center rounded-full px-3.5 text-[13px] font-semibold ring-1 ring-inset', 'bg-club text-ivory ring-club' => $preset === $key, 'bg-white text-ink/75 ring-line hover:text-ink' => $preset !== $key])>{{ $set['label'] }}</a>
            @endforeach
        </nav>
    </div>

    @if ($forming)
        <p class="rounded-xl border border-line bg-white px-3 py-3 text-[14px] text-ink/75">Season starts {{ $league->starts_on->format('M j') }}. Stats fill in after week 1.</p>
    @elseif ($stats->settledWeeks() === [])
        <p class="rounded-xl border border-line bg-white px-3 py-3 text-[14px] text-ink/75">Stats fill in after week 1 is final.</p>
    @else
        <div class="relative overflow-x-auto rounded-xl border border-line bg-white" data-stats-table>
            <table class="w-full min-w-[28rem] text-left text-sm">
                <thead class="gl-field text-[11px] tracking-wide text-ivory uppercase">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 bg-club py-2 pr-1 pl-3 font-semibold">Player</th>
                        @foreach ($shown as $key)
                            <th scope="col" class="px-1.5 py-2 text-center font-semibold whitespace-nowrap" title="{{ $columns[$key]['title'] }}" @if ($column === $key) aria-sort="descending" @endif>
                                @if ($columns[$key]['value'])
                                    <a href="{{ route('leagues.standings', [$league, 'view' => 'stats', 'preset' => $preset, 'col' => $key]) }}" class="inline-flex min-h-9 items-center gap-0.5 underline-offset-2 hover:underline">{{ $columns[$key]['label'] }}@if ($column === $key) <span aria-hidden="true">&darr;</span>@endif</a>
                                @else
                                    {{ $columns[$key]['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($statRows as $row)
                        @php $member = $row['member']; $mine = $me && $member->is($me); $s = $row['stats']; @endphp
                        <tr @class(['bg-mint' => $mine]) data-stats-row="{{ $member->id }}">
                            <th scope="row" @class(['sticky left-0 z-10 py-1.5 pr-1 pl-3 font-normal', 'bg-mint' => $mine, 'bg-white' => ! $mine])>
                                <a href="{{ route('leagues.standings.player', [$league, $member]) }}" class="flex min-h-11 items-center gap-2">
                                    <span class="w-4 shrink-0 text-right text-xs font-semibold text-ink/70 tabular-nums">{{ $row['rank'] }}</span>
                                    <x-avatar :user="$member->user" class="size-7 text-[11px]" />
                                    <span class="max-w-[8rem] truncate font-semibold text-ink">{{ $member->displayName() }}</span>
                                </a>
                            </th>
                            @foreach ($shown as $key)
                                <td class="px-1.5 py-1.5 text-center text-ink tabular-nums">
                                    @switch($key)
                                        @case('record')
                                            {{ $row['wins'] }}&ndash;{{ $row['ties'] }}&ndash;{{ $row['losses'] }}
                                            @break
                                        @case('avg')
                                            {{ $s['avgPercent'] !== null ? $s['avgPercent'].'%' : '—' }}
                                            @break
                                        @case('best_week')
                                            {{ $s['bestWeek'] ? $s['bestWeek']['weeklyPoints'].' ★' : '—' }}
                                            @break
                                        @case('form')
                                            <span class="flex justify-center gap-0.5">
                                                @foreach ($s['form'] as $band)
                                                    @php [$classes, $letter, $label] = $chip($band); @endphp
                                                    <span class="grid size-5 place-items-center rounded text-[10px] font-bold {{ $classes }}" title="{{ $label }}"><span aria-hidden="true">{{ $letter }}</span><span class="sr-only">{{ $label }}</span></span>
                                                @endforeach
                                            </span>
                                            @break
                                        @default
                                            <span @class(['font-bold text-club' => $key === 'total'])>{{ $columns[$key]['value']($row) }}</span>
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-ink/70">
        <span>{{ $column ? 'Sorted by '.Str::lower($columns[$column]['title']).', highest first.' : 'Official order: total stars, then line stars, then Past-the-Line weeks.' }}</span>
        @if ($column)
            <a href="{{ route('leagues.standings', [$league, 'view' => 'stats', 'preset' => $preset]) }}" class="inline-flex min-h-11 items-center font-semibold text-club hover:underline" data-reset-order>Reset to official order</a>
        @endif
    </div>
</section>

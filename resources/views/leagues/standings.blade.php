{{-- Full standings: league highlights, the table (official order by default, other sorts on request), and the
     viewer's season so far against the league average. Every number comes from LeagueTable and LeagueStats. --}}
@php
    $first = fn ($member) => $member->team_name ?: Str::before($member->user->name, ' ');
    $chartMax = max(150, (int) collect($series)->flatMap(fn ($point) => [$point['mine'], $point['league']])->filter()->max());
    $chartWidth = 320;
    $chartHeight = 120;
    $x = fn (int $index) => count($series) > 1 ? round(64 + $index * ($chartWidth - 80) / (count($series) - 1), 1) : $chartWidth / 2;
    $y = fn (?int $value) => round($chartHeight - 14 - (min($value ?? 0, $chartMax) / $chartMax) * ($chartHeight - 28), 1);
    $points = fn (string $key) => collect($series)->map(fn ($point, $index) => $point[$key] === null ? null : $x($index).','.$y($point[$key]))->filter()->implode(' ');
@endphp

<x-layouts.app :title="'Standings · '.$league->name">
    <x-slot:hero>
        <x-page-header title="Standings" :back="route('leagues.show', $league)" :back-label="$league->name"
            :subtitle="$summary['weeksRemaining'] > 0 ? $summary['weeksRemaining'].' '.Str::plural('week', $summary['weeksRemaining']).' to go' : 'Season over'" />
    </x-slot:hero>

    <div class="space-y-4" data-first-content>
        @if ($summary['played'] === 0)
            <x-empty-state title="Standings fill in after week 1"
                message="Results go final a day after each GoalWeek ends. Until then, everyone's tied at zero and it's all to play for."
                action="This week's matchups" :href="route('matchups')" />
        @else
            {{-- League highlights. --}}
            <section aria-labelledby="league-stats" class="rounded-xl border border-line bg-white">
                <h2 id="league-stats" class="eyebrow px-3 pt-2.5 text-club">Season so far</h2>
                <dl class="grid grid-cols-2 gap-px bg-line sm:grid-cols-3" data-league-summary>
                    @foreach (array_filter([
                        $summary['leader'] ? ['Leader', $first($summary['leader']['member']), $summary['leader']['total'].' pts'] : null,
                        ['Avg % of plan', ($summary['avgPercent'] ?? '–').'%', 'everyone, every week'],
                        $summary['longestOnLine'] ? ['Longest on the line', $summary['longestOnLine']['weeks'].' '.Str::plural('wk', $summary['longestOnLine']['weeks']), $first($summary['longestOnLine']['member'])] : null,
                        $summary['bestWeek'] ? ['Best week', $summary['bestWeek']['points'].' pts', $first($summary['bestWeek']['member']).', week '.$summary['bestWeek']['week'].' ('.$summary['bestWeek']['percent'].'%)'] : null,
                        $summary['mostSmashed'] ? ['Most Smashed', $summary['mostSmashed']['count'].' '.Str::plural('week', $summary['mostSmashed']['count']), $first($summary['mostSmashed']['member'])] : null,
                        ['Weeks left', $summary['weeksRemaining'], 'of '.$league->weeksCount()],
                    ]) as [$label, $value, $note])
                        <div class="bg-white px-3 py-2">
                            <dt class="text-[11px] font-semibold text-ink/70">{{ $label }}</dt>
                            <dd class="truncate text-[17px] leading-tight font-bold text-club tabular-nums">{{ $value }}</dd>
                            <dd class="truncate text-xs text-ink/70">{{ $note }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- The viewer's weekly percent against the league average. --}}
            @if (count($series) > 0)
                <section aria-labelledby="season-chart" class="rounded-xl border border-line bg-white px-3 py-2.5" data-season-chart>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="season-chart" class="eyebrow text-club">Your % of plan, week by week</h2>
                        <span class="flex items-center gap-3 text-[11px] text-ink/75">
                            <span class="flex items-center gap-1"><span class="h-0.5 w-4 rounded bg-club"></span> You</span>
                            <span class="flex items-center gap-1"><span class="h-0.5 w-4 rounded border-t-2 border-dashed border-gold"></span> League average</span>
                        </span>
                    </div>
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="mx-auto mt-2 h-auto w-full max-w-xl" role="img" aria-labelledby="season-chart">
                        {{-- Guide lines at In line and the plan, labelled at the left: the plan above its line, In line below. --}}
                        @foreach ([[(int) round(App\GoalWeekResult::IN_LINE_AT * 100), 9, 'In line'], [100, -3, 'Plan']] as [$guide, $offset, $name])
                            <line x1="8" x2="{{ $chartWidth - 8 }}" y1="{{ $y($guide) }}" y2="{{ $y($guide) }}" stroke="#e5e2d8" stroke-width="1" />
                            <text x="8" y="{{ $y($guide) + $offset }}" font-size="8" fill="#5f6b63">{{ $name }} {{ $guide }}%</text>
                        @endforeach
                        <polyline points="{{ $points('league') }}" fill="none" stroke="#d4ac58" stroke-width="2" stroke-dasharray="5 4" />
                        <polyline points="{{ $points('mine') }}" fill="none" stroke="#176b45" stroke-width="2.5" stroke-linejoin="round" />
                        @foreach ($series as $index => $point)
                            @if ($point['mine'] !== null)
                                <circle cx="{{ $x($index) }}" cy="{{ $y($point['mine']) }}" r="3" fill="#176b45" />
                            @endif
                            <text x="{{ $x($index) }}" y="{{ $chartHeight - 2 }}" text-anchor="middle" font-size="9" fill="#5f6b63">W{{ $point['week'] }}</text>
                        @endforeach
                    </svg>
                    <table class="sr-only">
                        <caption>Your percent of plan and the league average by week</caption>
                        <tr><th scope="col">Week</th><th scope="col">You</th><th scope="col">League average</th></tr>
                        @foreach ($series as $point)
                            <tr><td>{{ $point['week'] }}</td><td>{{ $point['mine'] ?? '–' }}%</td><td>{{ $point['league'] ?? '–' }}%</td></tr>
                        @endforeach
                    </table>
                </section>
            @endif
        @endif

        {{-- The table, with a sort control. The official order is the default. --}}
        <section aria-labelledby="standings-title">
            <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                <h2 id="standings-title" class="eyebrow text-club">Table</h2>
                <nav aria-label="Sort the table" class="-mx-1 flex max-w-full gap-1 overflow-x-auto px-1 [scrollbar-width:none]">
                    @foreach (App\Services\LeagueStats::SORTS as $key => $label)
                        <a href="{{ route('leagues.standings', $key === 'points' ? [$league] : [$league, 'sort' => $key]) }}" @if ($sort === $key) aria-current="true" @endif
                            @class(['inline-flex min-h-9 shrink-0 items-center rounded-full px-3 text-xs font-semibold ring-1 ring-inset', 'bg-club text-ivory ring-club' => $sort === $key, 'bg-white text-ink/75 ring-line hover:text-ink' => $sort !== $key])>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <x-standings-table :rows="$rows" :league="$league" :me="$me" />
            @if ($sort !== 'points')
                <p class="mt-2 text-xs text-ink/70">Sorted by {{ Str::lower(App\Services\LeagueStats::SORTS[$sort]) }} for a look around. The official order is by points.</p>
            @endif
            <x-scoring-help class="mt-1" />
        </section>
    </div>
</x-layouts.app>

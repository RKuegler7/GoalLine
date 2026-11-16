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
        {{-- The switch: the table, or the league's numbers. --}}
        <x-segmented label="Table or League stats" class="mx-auto w-full max-w-sm [&_a]:min-h-12 [&_a]:text-[16px] [&_a]:font-bold" data-standings-switch :options="[
            ['label' => 'Table', 'href' => route('leagues.standings', $league), 'active' => $view === 'table'],
            ['label' => 'League stats', 'href' => route('leagues.standings', [$league, 'view' => 'league']), 'active' => $view === 'league'],
        ]" />

        @if ($view === 'table')
            <section aria-labelledby="standings-title">
                <h2 id="standings-title" class="sr-only">Table</h2>
                @if ($summary['played'] === 0 && $league->status() !== App\LeagueStatus::Forming)
                    <p class="mb-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[13px] text-ink/75" data-standings-early>Standings fill in after week 1. Results go final a day after each GoalWeek ends; until then, everyone's level.</p>
                @endif
                <x-standings-table :rows="$rows" :league="$league" :me="$me" :column="$column" />
                <x-scoring-help class="mt-1" />
            </section>
        @elseif ($summary['played'] === 0)
            <x-empty-state title="Standings fill in after week 1"
                message="Results go final a day after each GoalWeek ends. Until then, everyone's tied at zero and it's all to play for."
                action="This week's Matchups" :href="route('matchups')" />
        @else
            {{-- League highlights. --}}
            <section aria-labelledby="league-stats" class="rounded-xl border border-line bg-white">
                <h2 id="league-stats" class="eyebrow px-3 pt-2.5 text-club">Season so far</h2>
                <dl class="grid grid-cols-2 gap-px bg-line sm:grid-cols-3" data-league-summary>
                    @foreach (array_filter([
                        $summary['leader'] ? ['Leader', $first($summary['leader']['member']), $summary['leader']['total'].' '.Str::plural('star', $summary['leader']['total'])] : null,
                        ['Avg % of plan', ($summary['avgPercent'] ?? '–').'%', 'everyone, every week'],
                        $summary['longestOnLine'] ? ['Longest on the line', $summary['longestOnLine']['weeks'].' '.Str::plural('wk', $summary['longestOnLine']['weeks']), $first($summary['longestOnLine']['member'])] : null,
                        $summary['bestWeek'] ? ['Best week', $summary['bestWeek']['points'].' '.Str::plural('star', $summary['bestWeek']['points']), $first($summary['bestWeek']['member']).', week '.$summary['bestWeek']['week'].' ('.$summary['bestWeek']['percent'].'%)'] : null,
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
                        @foreach ([[(int) round(App\GoalWeekResult::IN_LINE_AT * 100), 9, 'In Line'], [100, -3, 'Plan']] as [$guide, $offset, $name])
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
            <x-scoring-help />
        @endif
    </div>
</x-layouts.app>

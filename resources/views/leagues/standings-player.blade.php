{{-- One player's season in a league, for league members: the record and its close calls, points by kind, average
     percent of plan, best week, weeks on the line, bands, this week's status, and every week's result. No private
     notes, reflections, intake answers or email. --}}
@php
    $name = $member->displayName();
    $bandLabels = [
        App\GoalWeekResult::Short->value => 'Short',
        App\GoalWeekResult::InLine->value => 'In line',
        App\GoalWeekResult::Past->value => 'Past',
        App\GoalWeekResult::Smashed->value => 'Smashed',
    ];
@endphp

<x-layouts.app :title="$name.' · Standings · '.$league->name">
    <x-slot:hero>
        <x-page-header :title="$name" :eyebrow="$league->name.' · rank '.$row['rank']" :back="route('leagues.standings', $league)" back-label="Standings" />
    </x-slot:hero>

    <div class="space-y-4" data-first-content data-player-season>
        <section class="gl-field flex items-center gap-3 rounded-xl px-3 py-3 text-ivory">
            <x-avatar :user="$member->user" tone="light" class="size-14 text-lg ring-3 ring-ivory/70" />
            <div class="min-w-0">
                <p class="truncate text-[19px] leading-tight font-extrabold">{{ $name }}</p>
                @if ($member->team_name)
                    <p class="truncate text-xs text-mint">{{ $member->user->name }}</p>
                @endif
                <p class="mt-0.5 text-sm font-semibold text-ivory tabular-nums">{{ $player['wins'] }}&ndash;{{ $player['ties'] }}&ndash;{{ $player['losses'] }} <span class="font-normal text-ivory/90">&middot; {{ $player['photoFinishWins'] }} photo finish {{ Str::plural('win', $player['photoFinishWins']) }}, {{ $player['photoFinishes'] }} photo {{ Str::plural('finish', $player['photoFinishes']) }}{{ $player['byes'] ? ', '.$player['byes'].' '.Str::plural('bye', $player['byes']) : '' }}</span></p>
            </div>
            @if ($member->user->username)
                <a href="{{ route('profiles.show', $member->user) }}" class="ml-auto shrink-0 rounded-full bg-ivory/15 px-3 py-1.5 text-xs font-semibold text-ivory hover:bg-ivory/25">Profile</a>
            @endif
        </section>

        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-4" data-player-stats>
            @foreach ([
                ['Total stars', $player['total'], 'line + matchup'],
                ['Matchup stars', $player['matchPoints'], 'from matchups'],
                ['Line stars', $player['linePoints'], 'from bands'],
                ['Avg % of plan', $player['avgPercent'] === null ? '–' : $player['avgPercent'].'%', 'across final weeks'],
                ['Best week', $player['bestWeek'] ? $player['bestWeek']['weeklyPoints'].' '.Str::plural('star', $player['bestWeek']['weeklyPoints']) : '–', $player['bestWeek'] ? 'Week '.$player['bestWeek']['week'].', '.$player['bestWeek']['percent'].'%' : 'none yet'],
                ['Weeks on the line', $player['weeksOnLine'], 'best run: '.$player['bestWeeksOnLine']],
                ['This week', $player['goalStatus']?->label() ?? '–', $player['goalStatus'] ? 'live, so far' : 'no live week'],
                ['Plan', $player['planStatus'] ?? '–', 'this week'],
            ] as [$label, $value, $note])
                <div class="bg-white px-3 py-2">
                    <dt class="text-[11px] font-semibold text-ink/70">{{ $label }}</dt>
                    <dd class="truncate text-[17px] leading-tight font-bold text-club tabular-nums">{{ $value }}</dd>
                    <dd class="truncate text-xs text-ink/70">{{ $note }}</dd>
                </div>
            @endforeach
        </dl>

        <section aria-labelledby="player-bands" class="rounded-xl border border-line bg-white px-3 py-2.5">
            <h2 id="player-bands" class="eyebrow text-club">Weeks in each band</h2>
            <ul class="mt-2 grid grid-cols-4 gap-1.5 text-center" data-band-counts>
                @foreach ($bandLabels as $band => $label)
                    <li class="rounded-lg bg-ivory px-1 py-1.5">
                        <span class="block text-lg font-bold text-ink tabular-nums">{{ $player['bands'][$band] }}</span>
                        <span class="block text-[11px] text-ink/70">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="player-weeks">
            <h2 id="player-weeks" class="eyebrow mb-1.5 text-club">Week by week</h2>
            @if ($player['weeks'] === [])
                <x-empty-state message="No final weeks yet. Results land a day after each GoalWeek ends." />
            @else
                <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line bg-white" data-player-weeks>
                    @foreach (array_reverse($player['weeks']) as $week)
                        <li>
                            <a href="{{ route('leagues.matchups', [$league, $week['week']]) }}" class="flex items-center gap-3 px-3 py-2 hover:bg-mint/40">
                                <span class="w-10 shrink-0 text-xs font-semibold text-ink/70">Wk {{ $week['week'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] text-ink"><span class="font-semibold">{{ $week['outcome']->label() }}</span> vs {{ $week['opponentName'] }}</span>
                                    <span class="block truncate text-xs text-ink/70 tabular-nums">{{ $week['percent'] }}%{{ $week['opponentPercent'] !== null ? ' to '.$week['opponentPercent'].'%' : '' }}</span>
                                </span>
                                @if ($week['band'])
                                    <x-goalweek-result :result="$week['band']" compact />
                                @endif
                                <span class="w-12 shrink-0 text-right text-sm font-bold text-club tabular-nums">{{ $week['weeklyPoints'] }} {{ Str::plural('star', $week['weeklyPoints']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>

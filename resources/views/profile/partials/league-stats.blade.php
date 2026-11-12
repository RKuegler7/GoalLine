{{-- A profile's Stats: this person's numbers in one GoalLeague, from the same services as the league's standings. Only
     leagues the viewer is in too (or every league on your own locker), with a selector when there are several. --}}
@php
    $row = $leagueStats['row'];
    $league = $leagueStats['league'];
@endphp

@if ($league)
    <section class="mt-6 rounded-2xl border border-line bg-white px-4 py-3" aria-labelledby="profile-stats" data-profile-stats>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="profile-stats" class="eyebrow text-club">Stats</h2>
            @if ($leagueStats['leagues']->count() > 1)
                <form method="GET" action="{{ $user->profileUrl() }}#profile-stats" class="flex items-center gap-1.5">
                    <label for="stats-league" class="sr-only">GoalLeague</label>
                    <select id="stats-league" name="league" onchange="this.form.requestSubmit()" class="min-h-11 rounded-lg border border-line bg-white px-2 text-[14px]">
                        @foreach ($leagueStats['leagues'] as $choice)
                            <option value="{{ $choice->id }}" @selected($choice->is($league))>{{ $choice->name }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="min-h-11 rounded-full px-3 text-[13px] font-semibold text-club">Show</button></noscript>
                </form>
            @else
                <a href="{{ route('leagues.standings', [$league, 'view' => 'stats']) }}" class="text-[13px] font-semibold text-club hover:underline">{{ $league->name }} stats &rarr;</a>
            @endif
        </div>

        @if ($league->status() === App\LeagueStatus::Forming)
            <p class="mt-2 text-[14px] text-ink/75">Season starts {{ $league->starts_on->format('M j') }}. Stats fill in after week 1.</p>
        @elseif (! $row || $row['played'] === 0)
            <p class="mt-2 text-[14px] text-ink/75">No settled weeks yet in {{ $league->name }}.</p>
        @else
            @php $s = $row['stats']; @endphp
            <dl class="mt-2 grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                @foreach ([
                    ['Rank', $row['rank'].' of '.$league->members->count()],
                    ['Total stars', $row['total']],
                    ['Line stars', $row['resultPoints']],
                    ['Matchup stars', $row['matchupPoints']],
                    ['W–D–L', $row['wins'].'–'.$row['ties'].'–'.$row['losses']],
                    ['Photo finish wins', $s['photoFinishWins']],
                    ['Avg % of plan', $s['avgPercent'] !== null ? $s['avgPercent'].'%' : '—'],
                    ['On the line', $s['weeksOnLine'].' now · best '.$s['bestWeeksOnLine']],
                    ['Smashed · Past', $s['bands']['smashed'].' · '.$s['bands']['past']],
                    ['In line · Short', $s['bands']['in_line'].' · '.$s['bands']['short']],
                    ['Best week', $s['bestWeek'] ? $s['bestWeek']['weeklyPoints'].' ★ (week '.$s['bestWeek']['week'].')' : '—'],
                ] as [$label, $value])
                    <div class="rounded-lg bg-ivory px-2.5 py-2 ring-1 ring-line ring-inset">
                        <dt class="truncate text-[11px] font-semibold text-ink/70">{{ $label }}</dt>
                        <dd class="text-[16px] leading-tight font-bold text-ink tabular-nums">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($isOwn)
            <a href="{{ route('dashboard', ['tab' => 'history']) }}" class="mt-2 inline-flex min-h-10 items-center text-[13px] font-semibold text-club hover:underline">Your seasons and medals in History &rarr;</a>
        @endif
    </section>
@endif

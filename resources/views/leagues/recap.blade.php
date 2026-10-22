@php
    $standings = $table->standings();
    $podium = $standings->take(3);
    $nameOf = fn (?int $userId) => $league->members->firstWhere('user_id', $userId)?->user->name ?? 'Former member';
@endphp

<x-layouts.app :title="($complete ? 'Season recap' : 'Recaps').' · '.$league->name">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.show', $league) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $league->name }}</a>
            <p class="eyebrow mt-6 {{ $complete ? 'text-gold' : 'text-mint' }}">
                {{ $complete ? 'Season recap' : 'Weekly recaps' }} &middot; {{ $league->starts_on->format('M j') }} to {{ $league->ends_on->format('M j, Y') }}
            </p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $league->name }}</h1>

            @if ($complete && $podium->isNotEmpty())
                {{-- The podium: second, first, third. --}}
                <ol class="mt-8 grid grid-cols-3 items-end gap-2 sm:gap-4" aria-label="Final standings, top three">
                    @foreach ([1, 0, 2] as $place)
                        @if ($row = $podium->get($place))
                            <li class="flex flex-col items-center text-center" style="order: {{ [1 => 0, 0 => 1, 2 => 2][$place] }}">
                                <x-avatar :user="$row['member']->user" tone="light" :class="Arr::toCssClasses(['text-sm ring-4', 'size-20 ring-gold' => $place === 0, 'size-14 ring-ivory/40' => $place !== 0])" />
                                <span class="mt-2 max-w-full truncate text-sm font-semibold text-ivory">{{ $row['member']->user->name }}</span>
                                <span class="text-xs text-ivory/70 tabular-nums">{{ $row['total'] }} pts</span>
                                <span @class([
                                    'mt-2 flex w-full items-start justify-center rounded-t-xl pt-2 font-serif text-2xl font-medium',
                                    'h-24 bg-gold text-ink' => $place === 0,
                                    'h-16 bg-ivory/20 text-ivory' => $place === 1,
                                    'h-12 bg-ivory/10 text-ivory' => $place === 2,
                                ])>{{ $place + 1 }}</span>
                            </li>
                        @endif
                    @endforeach
                </ol>
            @elseif (! $complete)
                <p class="mt-3 max-w-xl text-ivory/85">Each GoalWeek&rsquo;s results land here once check-ins close. When the season ends, the awards come out: Champion, MVP, Comeback Player, Iron Streak, and Smash Machine.</p>
            @endif
        </div>
    </x-slot:hero>

    @if ($complete)
        {{-- Season awards. --}}
        <section>
            <h2 class="eyebrow text-club">Season awards</h2>
            @if ($awards->isEmpty())
                <p class="mt-3 rounded-2xl border border-line bg-white px-5 py-4 text-ink/65">No awards this season. Nobody put points on the board.</p>
            @else
                <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($awards as $award)
                        <li @class([
                            'flex items-center gap-4 rounded-2xl border px-5 py-4',
                            'border-gold bg-gold/15 sm:col-span-2 lg:col-span-1' => $award->badge === App\Badge::Champion,
                            'border-gold/50 bg-white' => $award->badge !== App\Badge::Champion,
                        ])>
                            <x-badge :badge="$award->badge" class="size-16" />
                            <div class="min-w-0">
                                <p class="eyebrow text-[0.62rem] text-ink/55">{{ $award->badge->label() }}</p>
                                <p class="mt-0.5 truncate font-serif text-xl font-medium text-ink">{{ $award->user->name }}</p>
                                <p class="text-sm text-ink/60">{{ $award->detail }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Final table. --}}
        <section class="mt-8">
            <h2 class="eyebrow text-club">Final table</h2>
            <div class="mt-3 overflow-x-auto rounded-2xl border border-line bg-white">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-ink/50">
                        <tr class="border-b border-line">
                            <th class="px-4 py-2 font-semibold">#</th>
                            <th class="px-4 py-2 font-semibold">Member</th>
                            <th class="px-4 py-2 text-right font-semibold">W&ndash;T&ndash;L</th>
                            <th class="px-4 py-2 text-right font-semibold">Result pts</th>
                            <th class="px-4 py-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($standings as $index => $row)
                            <tr @class(['bg-gold/10' => $index === 0])>
                                <td class="px-4 py-2.5 font-semibold text-ink/60 tabular-nums">{{ $index + 1 }}</td>
                                <td class="px-4 py-2.5 font-medium text-ink">{{ $row['member']->user->name }}</td>
                                <td class="px-4 py-2.5 text-right text-ink/70 tabular-nums">{{ $row['wins'] }}&ndash;{{ $row['ties'] }}&ndash;{{ $row['losses'] }}</td>
                                <td class="px-4 py-2.5 text-right text-ink/70 tabular-nums">{{ $row['resultPoints'] }}</td>
                                <td class="px-4 py-2.5 text-right font-bold text-ink tabular-nums">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Week by week. --}}
    <section @class(['mt-8' => $complete])>
        <h2 class="eyebrow text-club">Week by week</h2>
        @if ($weeks->isEmpty())
            <p class="mt-3 rounded-2xl border border-line bg-white px-5 py-4 text-ink/65">The first recap lands the day after week 1&rsquo;s check-ins close.</p>
        @else
            <div class="mt-3 space-y-3">
                @foreach ($weeks as $recapWeek)
                    <article class="overflow-hidden rounded-2xl border border-line bg-white">
                        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-ivory/60 px-5 py-3">
                            <h3 class="font-serif text-lg font-medium text-club">
                                Week {{ $recapWeek['week'] }}
                                <span class="font-sans text-sm font-normal text-ink/50">&middot; {{ $league->weekStartsOn($recapWeek['week'])->format('M j') }}&ndash;{{ $league->weekEndsOn($recapWeek['week'])->format('M j') }}</span>
                            </h3>
                            @if ($recapWeek['players']->isNotEmpty())
                                <span class="flex items-center gap-2 text-sm">
                                    <x-badge :badge="App\Badge::Mvp" class="size-6" />
                                    <span class="text-ink/60">Player of the Week:</span>
                                    <span class="font-semibold text-ink">{{ $recapWeek['players']->map(fn ($member) => $member->user->name)->join(', ', ' and ') }}</span>
                                    <span class="text-ink/50 tabular-nums">+{{ $recapWeek['best'] }}</span>
                                </span>
                            @endif
                        </header>
                        <ul class="divide-y divide-line">
                            @foreach ($recapWeek['matchups'] as $matchup)
                                @php
                                    $home = $matchup['home'];
                                    $away = $matchup['away'];
                                @endphp
                                <li class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-5 py-3 text-sm">
                                    <span class="min-w-0">
                                        <span @class(['block truncate', 'font-semibold text-ink' => $home->outcome === App\MatchupOutcome::Win, 'text-ink/75' => $home->outcome !== App\MatchupOutcome::Win])>{{ $nameOf($home->user_id) }}</span>
                                        <x-goalweek-result :result="$home->result" class="mt-1" />
                                    </span>
                                    <span class="text-center font-serif text-lg tabular-nums text-ink">
                                        {{ $home->result_points }}<span class="mx-1 text-ink/30">&ndash;</span>{{ $away ? $away->result_points : '' }}
                                        @if (! $away)
                                            <span class="block text-xs font-sans text-ink/50">vs median</span>
                                        @endif
                                    </span>
                                    <span class="min-w-0 text-right">
                                        @if ($away)
                                            <span @class(['block truncate', 'font-semibold text-ink' => $away->outcome === App\MatchupOutcome::Win, 'text-ink/75' => $away->outcome !== App\MatchupOutcome::Win])>{{ $nameOf($away->user_id) }}</span>
                                            <x-goalweek-result :result="$away->result" class="mt-1" />
                                        @else
                                            <span class="block font-semibold text-ink/70">{{ $home->outcome->letter() === 'W' ? 'Beat the league' : ($home->outcome->letter() === 'T' ? 'Matched the league' : 'Below the league') }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>

{{-- One page of the matchup pager, fantasy-football style. The scoreboard up top: both players, their records, live
     percent of plan, band, projection, and points if the week ended now. Below, where the players would be: each
     side's tasks for the week, by day. Every number comes from MatchupPager; this view only shows them. --}}
@props(['page', 'league', 'week', 'index', 'count'])

@php
    $card = $page['card'];
    $sides = $page['sides'];
    $final = $page['final'];
    $viewer = auth()->user();
    $first = fn ($side) => Str::before($side['member']->user->name, ' ');
    $eyebrow = match ($page['kind']) {
        'solo' => 'Solo: against the line',
        'bye' => $page['mine'] ? 'Your bye week: you vs the league median' : 'Bye week: vs the league median',
        default => $page['mine'] ? 'Your matchup' : 'Matchup',
    };
@endphp

<article id="matchup-{{ $index + 1 }}" data-pager-page data-side-names="{{ json_encode(array_map($first, $sides)) }}"
    aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ $count }}"
    class="w-full shrink-0 snap-center snap-always px-4 sm:px-1">

    {{-- The scoreboard. --}}
    <section data-region="matchup-{{ $card['home']['member']->id }}" @class(['rounded-2xl border bg-white p-4 sm:p-5', 'border-club ring-4 ring-mint' => $page['mine'], 'border-line' => ! $page['mine']])>
        <div class="flex items-center justify-between gap-2">
            <p class="eyebrow text-[0.62rem] text-ink/50">{{ $eyebrow }}</p>
            <p @class(['eyebrow rounded-full px-2 py-0.5 text-[0.6rem]', 'bg-ink/5 text-ink/60' => $final, 'bg-mint text-club' => ! $final])>{{ $final ? 'Final' : 'Live' }}</p>
        </div>

        <div @class(['mt-4 grid gap-4', 'grid-cols-2' => $page['kind'] !== 'solo'])>
            @foreach ($sides as $sideIndex => $side)
                @php
                    $record = $side['record'];
                    $projection = $side['projection'];
                @endphp
                <div @class(['min-w-0', 'text-right' => $sideIndex === 1])>
                    <div @class(['flex items-center gap-2', 'flex-row-reverse' => $sideIndex === 1])>
                        <x-avatar :user="$side['member']->user" class="size-11 text-xs" />
                        @if ($page['kind'] !== 'solo' && ($final || $side['outcome']->isWin()))
                            <span @class([
                                'rounded-md px-1.5 py-0.5 text-[0.65rem] font-bold',
                                'bg-grass text-white' => $side['outcome']->isWin(),
                                'bg-mint text-club' => $side['outcome'] === App\MatchupOutcome::Tie,
                                'bg-ink/10 text-ink/60' => $side['outcome']->isLoss(),
                            ])>{{ $final ? $side['outcome']->label() : 'Ahead' }}</span>
                        @endif
                    </div>
                    <a href="{{ $side['highlightsUrl'] }}" class="mt-2 block truncate font-semibold text-ink hover:text-club hover:underline" title="{{ $side['member']->user->name }}'s posts in {{ $league->name }}">{{ $side['member']->user->name }}</a>
                    <p class="text-xs text-ink/55 tabular-nums" title="Wins, ties and losses this season">{{ $record['wins'] }}&ndash;{{ $record['ties'] }}&ndash;{{ $record['losses'] }} <span class="text-ink/40">W&ndash;T&ndash;L</span></p>
                    <p class="truncate text-xs text-ink/55">{{ $side['member']->goal?->title ?? 'No goal selected' }} &middot; {{ $league->name }}</p>

                    <p class="mt-3 font-serif text-4xl font-bold text-club tabular-nums tracking-tight">@if ($side['percent'] === null)–@else<span data-countup="{{ $side['percent'] }}" data-key="share-{{ $side['member']->id }}">{{ $side['percent'] }}</span>%@endif</p>
                    <p class="text-xs text-ink/55">{{ $side['earned'] }} of {{ $side['planned'] }} planned points</p>
                    <div @class(['mt-1 flex', 'justify-end' => $sideIndex === 1])>
                        <x-plan-status :week="$side['goalWeek']" :href="route('leagues.members.plan', [$league, $side['member']])" />
                    </div>
                    @if ($side['held'] > 0)
                        <p class="mt-1 text-xs text-ink/60">{{ $side['held'] }} {{ Str::plural('point', $side['held']) }} held while a dispute is settled</p>
                    @endif
                    <div @class(['mt-2 flex', 'justify-end' => $sideIndex === 1])>
                        @if ($side['result'])
                            <x-goalweek-result :result="$side['result']" :not-started="$side['notStarted']" :needs-proof="$side['needsProof']" />
                        @else
                            <span class="text-xs text-ink/50">No GoalWeek this week</span>
                        @endif
                    </div>
                    @if (! $final)
                        <div @class(['mt-2 text-xs text-ink/60', 'text-right' => $sideIndex === 1])>
                            <p>Projected, on current pace</p>
                            @if ($projection['band'])
                                <div @class(['mt-1 flex flex-wrap items-center gap-1.5', 'justify-end' => $sideIndex === 1])>
                                    <x-goalweek-result :result="$projection['band']" />
                                    <span class="tabular-nums">{{ $projection['percent'] }}%</span>
                                </div>
                            @else
                                <p class="mt-1 text-ink/45">Too early to tell</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($page['kind'] === 'bye')
                <div class="min-w-0 text-right">
                    <div class="flex justify-end"><span class="grid size-11 place-items-center rounded-full bg-ivory text-xs font-semibold text-ink/50 ring-1 ring-line">M</span></div>
                    <p class="mt-2 font-semibold text-ink">League median</p>
                    <p class="text-xs text-ink/55">Odd number of members: one plays the middle score</p>
                    <p class="mt-3 font-serif text-4xl font-bold text-ink/60 tabular-nums tracking-tight">{{ $card['median'] === null ? '–' : round($card['median'], 1) }}</p>
                    <p class="text-xs text-ink/55">line points{{ $card['medianPercent'] === null ? '' : ' · '.round($card['medianPercent'], 1).'% of plan' }}</p>
                </div>
            @endif
        </div>

        @if ($page['kind'] === 'solo')
            <p class="mt-3 rounded-xl bg-ivory px-4 py-3 text-center text-sm text-ink">It&rsquo;s you against the line this season. Invite someone and the matchups start.</p>
        @else
            @if ($card['home']['outcome']->isPhotoFinish() && ($card['away'] !== null || $card['median'] !== null))
                <p class="mt-3 flex justify-center"><span class="eyebrow rounded-full bg-gold/25 px-2.5 py-0.5 text-[0.6rem] text-ink" title="Same band, so the higher credited percent of plan takes it">Photo finish</span></p>
            @endif
            <p class="mt-3 rounded-xl bg-ivory px-4 py-3 text-center font-medium text-ink">{{ $card['verdict'] }}</p>
        @endif

        <div @class(['mt-3 grid gap-2 text-xs text-ink/65', 'grid-cols-2' => count($sides) > 1])>
            @foreach ($sides as $sideIndex => $side)
                <div @class(['rounded-lg border border-line px-3 py-2', 'text-right' => $sideIndex === 1])>
                    <p class="font-semibold text-ink">{{ $first($side) }}: {{ $final ? '' : 'if it ended now, ' }}+<span data-countup="{{ $side['total'] }}" data-key="total-{{ $side['member']->id }}">{{ $side['total'] }}</span> {{ Str::plural('pt', $side['total']) }}</p>
                    <p>{{ $side['linePoints'] }} line + {{ $side['matchPoints'] }} match &middot; {{ $side['outcome']->label() }}</p>
                    @if (! $final && $side['next'])
                        <p class="mt-0.5 text-club">{{ $side['next']['points'] }} more {{ Str::plural('point', $side['next']['points']) }} to {{ $side['next']['band']->label() }}</p>
                    @elseif (! $final && $side['result'] === App\GoalWeekResult::Smashed)
                        <p class="mt-0.5 text-club">Top band. Keep the streak going.</p>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="mt-3 flex flex-wrap items-center justify-center gap-x-2 text-xs text-ink/60">
            @if (! $final && $page['daysLeft'] > 0)
                <span class="font-semibold text-ink">{{ $page['daysLeft'] }} {{ Str::plural('day', $page['daysLeft']) }} left</span> <span aria-hidden="true">&middot;</span>
            @endif
            <span>{{ $page['status'] }}</span>
        </p>

        @php
            $opponent = $page['mine'] && $card['away']
                ? ($card['home']['member']->user->is($viewer) ? $card['away']['member'] : $card['home']['member'])
                : null;
        @endphp
        @if ($opponent && ! $final)
            <div class="mt-3 flex justify-end">
                <x-nudge-button :user="$opponent->user" />
            </div>
        @endif
    </section>

    {{-- Where the players would be: the week's tasks, by day. Side by side on wide screens, one at a time on phones. --}}
    <section class="mt-4" aria-label="This week's tasks">
        <div @class(['grid gap-4', 'md:grid-cols-2' => count($sides) > 1])>
            @foreach ($sides as $sideIndex => $side)
                <div data-player-panel="{{ $sideIndex }}" class="min-w-0 rounded-2xl border border-line bg-white p-3 sm:p-4">
                    <h3 class="px-1 font-serif text-lg font-bold text-club tracking-tight">{{ $first($side) }}&rsquo;s week</h3>
                    @if (! $side['goalWeek'])
                        <p class="mt-2 px-1 text-sm text-ink/55">No GoalWeek this week.</p>
                    @else
                        <ol class="mt-2 space-y-3">
                            @foreach ($side['days'] as $day)
                                <li>
                                    <p @class(['flex items-center gap-2 px-1 text-xs font-semibold', 'text-club' => $day['isToday'], 'text-ink/55' => ! $day['isToday']])>
                                        <span class="eyebrow text-[0.6rem]">{{ $day['label'] }}</span>
                                        <span>{{ $day['date']->format('M j') }}</span>
                                        @if ($day['isToday'])
                                            <span class="rounded-full bg-mint px-1.5 text-[0.6rem]">Today</span>
                                        @endif
                                    </p>
                                    @if ($day['rows'] === [])
                                        <p class="mt-1 rounded-xl bg-ivory px-3 py-2 text-xs text-ink/50">Rest day</p>
                                    @else
                                        <ul class="mt-1 space-y-1">
                                            @foreach ($day['rows'] as $row)
                                                <x-matchup-task-row :row="$row" />
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                            @if ($side['anytime'] !== [])
                                <li>
                                    <p class="px-1 text-xs font-semibold text-ink/55"><span class="eyebrow text-[0.6rem]">Any day</span> this week</p>
                                    <ul class="mt-1 space-y-1">
                                        @foreach ($side['anytime'] as $row)
                                            <x-matchup-task-row :row="$row" />
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- The check-in log, with proof requests, disputes and Smashed confirmations. --}}
    <div class="mt-4 rounded-2xl border border-line bg-white px-4 pb-3">
        <x-matchup-timeline :league="$league" :week="$week" :matchup="$card['matchup']" :open="false" :read-only="$page['readOnly']" />
    </div>
</article>

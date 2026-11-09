{{-- One page of the matchup pager, compact like a fantasy scoreboard. Up top: both players side by side with their
     record, goal, live percent (bold), band, projection and points if the week ended now. Below: both players' tasks
     in aligned rows, grouped by day, with day chips (today first) and a Today/Week toggle. Tapping a task opens the
     task sheet. Every number comes from MatchupPager; this view only shows them. --}}
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
    // Days, aligned across both players: Monday to Sunday.
    $dayList = collect($sides)->pluck('days')->first(fn ($days) => ! empty($days)) ?? [];
    $todayIndex = collect($dayList)->search(fn ($day) => $day['isToday']);
    $showWeek = $final || $todayIndex === false;
    $columns = count($sides) > 1 ? 'grid-cols-2' : 'grid-cols-1';
@endphp

<article id="matchup-{{ $index + 1 }}" data-pager-page data-matchup-days
    aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ $count }}"
    class="w-full shrink-0 snap-center snap-always px-4 sm:px-1">

    {{-- The scoreboard. --}}
    <section data-region="matchup-{{ $card['home']['member']->id }}" @class(['rounded-xl border bg-white p-3', 'border-club/50' => $page['mine'], 'border-line' => ! $page['mine']])>
        <div class="flex items-center justify-between gap-2 text-[11px]">
            <p class="eyebrow text-ink/70">{{ $eyebrow }}</p>
            <p class="flex items-center gap-1.5">
                @if (! $final && $page['daysLeft'] > 0)
                    <span class="font-semibold text-ink">{{ $page['daysLeft'] }} {{ Str::plural('day', $page['daysLeft']) }} left</span>
                @endif
                <span @class(['eyebrow rounded-full px-1.5 py-0.5', 'bg-ink/5 text-ink/70' => $final, 'bg-mint text-club' => ! $final])>{{ $final ? 'Final' : 'Live' }}</span>
            </p>
        </div>

        <div @class(['mt-2 grid items-start gap-2', 'grid-cols-[1fr_auto_1fr]' => $page['kind'] !== 'solo', 'grid-cols-1' => $page['kind'] === 'solo'])>
            @foreach ($sides as $sideIndex => $side)
                @if ($sideIndex === 1)
                    <span class="self-center pt-1 text-[11px] font-semibold text-ink/70">vs</span>
                @endif
                @php
                    $record = $side['record'];
                    $projection = $side['projection'];
                    $right = $sideIndex === 1;
                @endphp
                <div @class(['min-w-0', 'text-right' => $right])>
                    <div @class(['flex items-center gap-1.5', 'flex-row-reverse' => $right])>
                        <x-avatar :user="$side['member']->user" class="size-7 shrink-0 text-[11px]" />
                        <a href="{{ $side['highlightsUrl'] }}" class="min-w-0 truncate text-[13px] font-semibold text-ink hover:text-club hover:underline" title="{{ $side['member']->user->name }}'s posts in {{ $league->name }}">{{ $first($side) }}</a>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] text-ink/70"><span class="tabular-nums" title="Wins, ties and losses this season">{{ $record['wins'] }}&ndash;{{ $record['ties'] }}&ndash;{{ $record['losses'] }}</span> &middot; {{ $side['member']->goal?->title ?? 'No goal selected' }}</p>

                    <p class="mt-1 text-[20px] leading-tight font-bold text-club tabular-nums">@if ($side['percent'] === null)&ndash;@else<span data-countup="{{ $side['percent'] }}" data-key="share-{{ $side['member']->id }}">{{ $side['percent'] }}</span>%@endif</p>
                    <p class="text-[11px] text-ink/70 tabular-nums">{{ $side['earned'] }} of {{ $side['planned'] }} pts</p>
                    <div @class(['mt-1 flex', 'justify-end' => $right])>
                        @if ($side['result'])
                            <x-goalweek-result :result="$side['result']" :not-started="$side['notStarted']" :needs-proof="$side['needsProof']" compact />
                        @else
                            <span class="text-[11px] text-ink/70">No GoalWeek</span>
                        @endif
                    </div>
                    <div @class(['mt-1 flex', 'justify-end' => $right])>
                        <x-plan-status :week="$side['goalWeek']" :href="route('leagues.members.plan', [$league, $side['member']])" />
                    </div>
                    @if ($side['held'] > 0)
                        <p class="mt-0.5 text-[11px] text-ink/70">{{ $side['held'] }} {{ Str::plural('point', $side['held']) }} held while a dispute is settled</p>
                    @endif
                </div>
            @endforeach

            @if ($page['kind'] === 'bye')
                <span class="self-center pt-1 text-[11px] font-semibold text-ink/70">vs</span>
                <div class="min-w-0 text-right">
                    <div class="flex flex-row-reverse items-center gap-1.5"><span class="grid size-7 place-items-center rounded-full bg-ivory text-[11px] font-semibold text-ink/70 ring-1 ring-line">M</span><span class="text-[13px] font-semibold text-ink">League median</span></div>
                    <p class="mt-0.5 truncate text-[11px] text-ink/70">The middle score this week</p>
                    <p class="mt-1 text-[20px] leading-tight font-bold text-ink/70 tabular-nums">{{ $card['median'] === null ? '–' : round($card['median'], 1) }}</p>
                    <p class="text-[11px] text-ink/70">line pts{{ $card['medianPercent'] === null ? '' : ' · '.round($card['medianPercent'], 1).'%' }}</p>
                </div>
            @endif
        </div>

        {{-- Projected band and points if it ended now, in one compact row per player. --}}
        <div @class(['mt-2 grid gap-2 border-t border-line pt-2 text-[11px] text-ink/70', 'grid-cols-2' => count($sides) > 1])>
            @foreach ($sides as $sideIndex => $side)
                <div @class(['min-w-0', 'text-right' => $sideIndex === 1])>
                    @if (! $final)
                        <p class="truncate">Projected, on current pace</p>
                        <p class="truncate">
                            @if ($side['projection']['band'])
                                <span class="font-semibold text-ink">{{ $side['projection']['band']->label() }}</span> <span class="tabular-nums">{{ $side['projection']['percent'] }}%</span>
                            @else
                                Too early to tell
                            @endif
                        </p>
                    @endif
                    <p class="mt-1 truncate font-semibold text-ink">{{ $final ? '' : 'if it ended now, ' }}+<span data-countup="{{ $side['total'] }}" data-key="total-{{ $side['member']->id }}">{{ $side['total'] }}</span> {{ Str::plural('pt', $side['total']) }}</p>
                    <p class="truncate tabular-nums">{{ $side['linePoints'] }} line + {{ $side['matchPoints'] }} match &middot; {{ $side['outcome']->label() }}</p>
                    @if (! $final && $side['next'])
                        <p class="truncate text-club">{{ $side['next']['points'] }} more {{ Str::plural('point', $side['next']['points']) }} to {{ $side['next']['band']->label() }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($page['kind'] === 'solo')
            <p class="mt-2 rounded-lg bg-ivory px-3 py-2 text-center text-[13px] text-ink">It&rsquo;s you against the line this season. Invite someone and the matchups start.</p>
        @else
            <p class="mt-2 flex flex-wrap items-center justify-center gap-1.5 rounded-lg bg-ivory px-3 py-1.5 text-center text-[13px] font-medium text-ink">
                @if ($card['home']['outcome']->isPhotoFinish() && ($card['away'] !== null || $card['median'] !== null) && ! Str::startsWith($card['verdict'], 'Photo finish'))
                    <span class="eyebrow rounded-full bg-gold/25 px-1.5 py-0.5 text-[11px] text-ink" title="Same band, so the higher credited percent of plan takes it">Photo finish</span>
                @endif
                {{ $card['verdict'] }}
            </p>
        @endif

        @php
            $opponent = $page['mine'] && $card['away']
                ? ($card['home']['member']->user->is($viewer) ? $card['away']['member'] : $card['home']['member'])
                : null;
        @endphp
        <div class="mt-1.5 flex items-center justify-between gap-2 text-[11px] text-ink/70">
            <span class="truncate">{{ $page['status'] }}</span>
            @if ($opponent && ! $final)
                <x-nudge-button :user="$opponent->user" />
            @endif
        </div>
    </section>

    {{-- Both players' tasks, side by side, by day. --}}
    <section class="mt-2 rounded-xl border border-line bg-white" aria-label="This week's tasks">
        @if ($dayList !== [])
            <div class="flex items-center gap-1.5 border-b border-line px-2 py-1.5">
                <div class="flex flex-1 gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Day">
                    @foreach ($dayList as $dayIndex => $day)
                        <button type="button" data-day-chip="{{ $dayIndex }}" role="tab" aria-selected="{{ ! $showWeek && $dayIndex === $todayIndex ? 'true' : 'false' }}"
                            class="min-h-8 shrink-0 rounded-full px-2 text-[11px] font-semibold text-ink/70 ring-1 ring-line ring-inset aria-selected:bg-club aria-selected:text-ivory aria-selected:ring-club">
                            {{ $day['isToday'] ? 'Today' : $day['label'] }}
                        </button>
                    @endforeach
                </div>
                <button type="button" data-day-all aria-pressed="{{ $showWeek ? 'true' : 'false' }}"
                    class="min-h-8 shrink-0 rounded-full px-2.5 text-[11px] font-semibold text-club ring-1 ring-club/30 ring-inset aria-pressed:bg-club aria-pressed:text-ivory">Week</button>
            </div>
        @endif

        @if (count($sides) > 1)
            <div class="grid grid-cols-2 gap-2 border-b border-line px-2 py-1 text-[11px] font-semibold text-ink/70">
                @foreach ($sides as $sideIndex => $side)
                    <span @class(['truncate', 'text-right' => $sideIndex === 1])>{{ $first($side) }}</span>
                @endforeach
            </div>
        @endif

        <ol class="divide-y divide-line">
            @foreach ($dayList as $dayIndex => $day)
                <li data-day-group="{{ $dayIndex }}" @if (! $showWeek && $dayIndex !== $todayIndex) hidden @endif class="px-2 py-1.5">
                    <p @class(['mb-1 flex items-center gap-1.5 text-[11px] font-semibold', 'text-club' => $day['isToday'], 'text-ink/70' => ! $day['isToday']])>
                        <span class="eyebrow">{{ $day['label'] }}</span> <span>{{ $day['date']->format('M j') }}</span>
                        @if ($day['isToday'])
                            <span class="rounded-full bg-mint px-1.5">Today</span>
                        @endif
                    </p>
                    <div class="grid {{ $columns }} gap-2">
                        @foreach ($sides as $side)
                            @php $rows = $side['days'][$dayIndex]['rows'] ?? []; @endphp
                            <ul class="min-w-0 space-y-0.5">
                                @forelse ($rows as $row)
                                    <x-matchup-task-row :row="$row" />
                                @empty
                                    <li class="rounded-lg bg-ivory px-2 py-1.5 text-[11px] text-ink/70">{{ $side['goalWeek'] ? 'Rest day' : 'No GoalWeek' }}</li>
                                @endforelse
                            </ul>
                        @endforeach
                    </div>
                </li>
            @endforeach

            @if (collect($sides)->contains(fn ($side) => $side['anytime'] !== []))
                <li class="px-2 py-1.5">
                    <p class="mb-1 text-[11px] font-semibold text-ink/70"><span class="eyebrow">Any day</span> this week</p>
                    <div class="grid {{ $columns }} gap-2">
                        @foreach ($sides as $side)
                            <ul class="min-w-0 space-y-0.5">
                                @foreach ($side['anytime'] as $row)
                                    <x-matchup-task-row :row="$row" />
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                </li>
            @endif

            @if ($dayList === [])
                <li class="px-3 py-2 text-[13px] text-ink/70">No GoalWeek this week.</li>
            @endif
        </ol>
    </section>

    {{-- The check-in log, with proof requests, disputes and Smashed confirmations. --}}
    <div data-region="matchup-log-{{ $card['home']['member']->id }}" class="mt-2 rounded-xl border border-line bg-white px-3 pb-2">
        {{-- Right after a dispute action, the log opens on its own so you can see what changed. --}}
        <x-matchup-timeline :league="$league" :week="$week" :matchup="$card['matchup']" :open="$page['mine'] && (Str::contains((string) session('status'), ['ispute', 'Ruled']) || $errors->getBag('dispute')->any())" :read-only="$page['readOnly']" />
    </div>
</article>

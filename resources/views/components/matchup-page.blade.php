{{-- One page of the matchup pager. The live numbers stick to the top in the strip above the pager (matchup-strip);
     this page grows naturally so the whole document scrolls. Here: the verdict, each player's plain-language targets
     ("To reach In line: 3 more points, about 3 tasks"), tasks done and left, a week timeline, both players' tasks side
     by side by day (the sticky day chips choose the day), and the check-in log. Every number comes from MatchupPager
     and the scoring code; this view only shows them. Encouraging, never shaming. --}}
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
    $dayList = collect($sides)->pluck('days')->first(fn ($days) => ! empty($days)) ?? [];
    $todayIndex = collect($dayList)->search(fn ($day) => $day['isToday']);
    $showWeek = $final || $todayIndex === false;
    $columns = count($sides) > 1 ? 'grid-cols-2' : 'grid-cols-1';
    $pts = fn ($points) => App\Points::format((float) $points).' '.Str::plural('point', (int) ceil((float) $points));
    $opponent = $page['mine'] && $card['away']
        ? ($card['home']['member']->user->is($viewer) ? $card['away']['member'] : $card['home']['member'])
        : null;
    // Each day as done, due, upcoming, rest, or (neutral) not done.
    $dayState = function (array $day) {
        $rows = collect($day['rows']);

        return match (true) {
            $rows->isEmpty() => 'rest',
            $rows->every(fn ($row) => $row['status'] === App\Services\MatchupPager::STATUS_DONE) => 'done',
            $day['isToday'] => 'due',
            $day['date']->isFuture() => 'upcoming',
            default => 'open',
        };
    };
@endphp

<article id="matchup-{{ $index + 1 }}" data-pager-page aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ $count }}"
    class="w-full shrink-0 snap-center px-4 sm:px-1">

    {{-- The verdict, and each player's targets. --}}
    <section data-region="matchup-{{ $card['home']['member']->id }}" @class(['rounded-xl border bg-white p-3', 'border-club/50' => $page['mine'], 'border-line' => ! $page['mine']])>
        <div class="flex items-center justify-between gap-2">
            <p class="eyebrow text-[11px] text-ink/70">{{ $eyebrow }}</p>
            <p class="truncate text-[11px] text-ink/70">@if (! $final && $page['daysLeft'] > 0)<span class="font-semibold text-ink">{{ $page['daysLeft'] }} {{ Str::plural('day', $page['daysLeft']) }} left</span> &middot; @endif{{ $page['status'] }}</p>
        </div>

        @if ($page['kind'] === 'solo')
            <x-empty-state class="mt-2" message="It’s you against the line this season. Invite someone and the matchups start." action="Invite friends" :href="route('leagues.show', $league).'#invite'" />
        @else
            <p class="mt-2 flex flex-wrap items-center justify-center gap-1.5 rounded-lg bg-ivory px-3 py-1.5 text-center text-[13px] font-medium text-ink">
                @if ($card['home']['outcome']->isPhotoFinish() && ($card['away'] !== null || $card['median'] !== null) && ! Str::startsWith($card['verdict'], 'Photo finish'))
                    <span class="eyebrow rounded-full bg-gold/25 px-1.5 py-0.5 text-[11px] text-ink" title="Same band, so the higher credited percent of plan takes it">Photo finish</span>
                @endif
                {{ $card['verdict'] }}
            </p>
        @endif

        <div @class(['mt-2 grid gap-3', $columns])>
            @foreach ($sides as $sideIndex => $side)
                @php
                    $right = $sideIndex === 1;
                    $targets = $side['targets'];
                @endphp
                <div @class(['min-w-0 text-[12px] text-ink/80', 'text-right' => $right]) data-player-detail>
                    <p class="truncate text-[13px] font-semibold text-ink">
                        <a href="{{ $side['highlightsUrl'] }}" class="hover:text-club hover:underline" title="{{ $side['member']->user->name }}'s posts in {{ $league->name }}">{{ $first($side) }}</a>
                        <span class="font-normal text-ink/70">&middot; {{ $side['earned'] }} of {{ $side['planned'] }} pts</span>
                    </p>
                    <div @class(['mt-1 flex', 'justify-end' => $right])>
                        <x-plan-status :week="$side['goalWeek']" :href="route('leagues.members.plan', [$league, $side['member']])" />
                    </div>
                    @if ($side['goalWeek'])
                        <x-band-track :week="$side['goalWeek']" compact />
                        @if ($side['member']->user_id === auth()->id() && $side['goalWeek']->isStarted())
                            <a href="{{ route('cards.share', $side['goalWeek']) }}" data-share-week
                                class="mt-1 inline-flex min-h-8 items-center gap-1 rounded-full border border-club/30 px-2.5 text-[12px] font-semibold text-club hover:border-club">
                                <x-icon name="share" class="size-3.5" /> Share your week
                            </a>
                        @endif
                    @endif

                    @if ($targets)
                        {{-- Plain-language targets, from the scoring code. --}}
                        <ul class="mt-1.5 space-y-0.5">
                            @if (! $final && $side['next'])
                                @php
                                    $goal = $side['next']['band'] === App\GoalWeekResult::InLine ? $targets['inLine'] : ($side['next']['band'] === App\GoalWeekResult::Past ? $targets['past'] : null);
                                @endphp
                                <li class="font-semibold text-club">{{ $side['next']['points'] }} more {{ Str::plural('point', $side['next']['points']) }} to {{ $side['next']['band']->label() }}@if ($goal && $targets['left'] > 0) <span class="font-normal text-ink/70">({{ $goal['more'] ? 'all '.$targets['left'].' '.Str::plural('task', $targets['left']).', plus habit days or the milestone' : 'about '.$goal['tasks'].' '.Str::plural('task', $goal['tasks']) }})</span>@endif</li>
                                @if ($side['next']['band'] === App\GoalWeekResult::InLine)
                                    <li>To reach Past the line: {{ $pts($targets['past']['points']) }}</li>
                                @endif
                            @elseif (! $final && $side['result'] === App\GoalWeekResult::Smashed)
                                <li class="font-semibold text-club">Top band. Keep the streak going.</li>
                            @endif
                            <li><span class="font-semibold text-ink">{{ $targets['done'] }} done</span> &middot; {{ $targets['left'] }} to go</li>
                        </ul>

                        {{-- The week at a glance: done, due, upcoming, rest. --}}
                        @if (! empty($side['days']))
                            <ol @class(['mt-1.5 flex gap-1', 'justify-end' => $right]) aria-label="{{ $first($side) }}'s week">
                                @foreach ($side['days'] as $day)
                                    @php $state = $dayState($day); @endphp
                                    <li title="{{ $day['label'] }}: {{ ['done' => 'done', 'due' => 'due today', 'upcoming' => 'upcoming', 'rest' => 'rest day', 'open' => 'not done'][$state] }}"
                                        @class([
                                            'grid size-5 place-items-center rounded-full text-[10px] font-bold',
                                            'bg-grass text-ink' => $state === 'done',
                                            'bg-gold/30 text-ink ring-1 ring-gold' => $state === 'due',
                                            'bg-ivory text-ink/70 ring-1 ring-line' => in_array($state, ['upcoming', 'open'], true),
                                            'text-ink/65' => $state === 'rest',
                                        ])><span aria-hidden="true">{{ Str::substr($day['label'], 0, 1) }}</span><span class="sr-only">{{ $day['label'] }} {{ $state }}</span></li>
                                @endforeach
                            </ol>
                        @endif
                    @else
                        <p class="mt-1.5 text-ink/70">No GoalWeek this week.</p>
                    @endif

                    @if (! $final)
                        <p class="mt-1.5 text-[11px] text-ink/70">Projected, on current pace</p>
                        <p class="text-[12px]">
                            @if ($side['projection']['band'])
                                <span class="font-semibold text-ink">{{ $side['projection']['band']->label() }}</span> <span class="tabular-nums">{{ $side['projection']['percent'] }}%</span>
                            @else
                                Too early to tell
                            @endif
                        </p>
                    @endif
                    <p class="mt-1 font-semibold text-ink">{{ $final ? '' : 'if it ended now, ' }}+<span data-countup="{{ $side['total'] }}" data-key="total-{{ $side['member']->id }}">{{ $side['total'] }}</span> {{ Str::plural('pt', $side['total']) }}</p>
                    <p class="tabular-nums text-ink/70">{{ $side['linePoints'] }} line + {{ $side['matchPoints'] }} match &middot; {{ $side['outcome']->label() }}</p>
                    @if ($side['held'] > 0)
                        <p class="mt-0.5 text-[11px] text-ink/70">{{ $side['held'] }} {{ Str::plural('point', $side['held']) }} held while a dispute is settled</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($opponent && ! $final)
            <div class="mt-2 flex justify-end">
                <x-nudge-button :user="$opponent->user" />
            </div>
        @endif
    </section>

    {{-- Both players' tasks, side by side, by day. The sticky day chips above choose the day. --}}
    <section class="mt-2 rounded-xl border border-line bg-white" aria-label="This week's tasks">
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

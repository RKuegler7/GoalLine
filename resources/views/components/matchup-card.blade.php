{{-- One matchup, built to read at a glance: both sides' share of their plan on the same line meter, their bands, who's
     ahead, what each would take if the week ended now, and how far each is from the next band. --}}
@props(['card', 'league', 'week', 'final'])

@php
    $sides = array_values(array_filter([$card['home'], $card['away']]));
    $fill = fn ($result) => match ($result) {
        App\GoalWeekResult::Short => 'bg-ink/30',
        App\GoalWeekResult::InLine => 'bg-grass',
        App\GoalWeekResult::Past => 'bg-gold',
        App\GoalWeekResult::Smashed => 'bg-sky-600',
        default => 'bg-transparent',
    };
    $marks = [
        ['share' => App\GoalWeekResult::IN_LINE_AT, 'label' => '90%'],
        ['share' => App\GoalWeekResult::PAST_ABOVE, 'label' => '100%'],
        ['share' => App\GoalWeekResult::SMASHED_AT, 'label' => '150%'],
    ];
    $viewer = auth()->user();
    $opponent = $card['mine'] && $card['away']
        ? ($card['home']['member']->user->is($viewer) ? $card['away']['member'] : $card['home']['member'])
        : null;
@endphp

<article data-region="matchup-{{ $card['home']['member']->id }}" {{ $attributes->class(['flex flex-col rounded-2xl border bg-white p-5', 'border-club ring-4 ring-mint' => $card['mine'], 'border-line' => ! $card['mine']]) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="eyebrow text-[0.62rem] text-ink/50">{{ $card['mine'] ? 'Your matchup' : 'Matchup' }}</p>
        <p @class(['eyebrow rounded-full px-2 py-0.5 text-[0.6rem]', 'bg-ink/5 text-ink/60' => $final, 'bg-mint text-club' => ! $final])>{{ $final ? 'Final' : 'Live' }}</p>
    </div>

    {{-- Head to head. --}}
    <div class="mt-4 grid grid-cols-[1fr_auto_1fr] items-start gap-3">
        @foreach ([$card['home'], $card['away']] as $index => $side)
            @if ($index === 1)
                <p class="mt-6 font-serif text-sm text-ink/40 tracking-tight">vs</p>
            @endif
            <div @class(['min-w-0', 'text-right' => $index === 1])>
                @if ($side)
                    <div @class(['flex items-center gap-2', 'flex-row-reverse' => $index === 1])>
                        <x-avatar :user="$side['member']->user" class="size-10 text-xs" />
                        @if ($final || $side['outcome']->isWin())
                            <span @class([
                                'rounded-md px-1.5 py-0.5 text-[0.65rem] font-bold',
                                'bg-grass text-white' => $side['outcome']->isWin(),
                                'bg-mint text-club' => $side['outcome'] === App\MatchupOutcome::Tie,
                                'bg-ink/10 text-ink/60' => $side['outcome']->isLoss(),
                            ])>{{ $final ? $side['outcome']->label() : 'Ahead' }}</span>
                        @endif
                    </div>
                    <p class="mt-2 truncate font-semibold text-ink">{{ $side['member']->user->name }}</p>
                    <p class="truncate text-xs text-ink/55">{{ $side['member']->goal?->title ?? 'No goal selected' }}</p>
                    <p class="mt-3 font-serif text-4xl font-bold text-club tabular-nums tracking-tight">@if ($side['percent'] === null)–@else<span data-countup="{{ $side['percent'] }}" data-key="share-{{ $side['member']->id }}">{{ $side['percent'] }}</span>%@endif</p>
                    <p class="text-xs text-ink/55">{{ $side['earned'] }} of {{ $side['planned'] }} planned points</p>
                    <div @class(['mt-1 flex', 'justify-end' => $index === 1])>
                        <x-plan-status :week="$side['goalWeek']" :href="route('leagues.members.plan', [$league, $side['member']])" />
                    </div>
                    @if ($side['held'] > 0)
                        <p class="mt-1 text-xs text-ink/60">{{ $side['held'] }} {{ Str::plural('point', $side['held']) }} held while a dispute is settled</p>
                    @endif
                    <div @class(['mt-2 flex', 'justify-end' => $index === 1])>
                        @if ($side['result'])
                            <x-goalweek-result :result="$side['result']" :not-started="$side['notStarted']" :needs-proof="$side['needsProof']" />
                        @else
                            <span class="text-xs text-ink/50">No GoalWeek this week</span>
                        @endif
                    </div>
                @else
                    <div class="flex justify-end"><span class="grid size-10 place-items-center rounded-full bg-ivory text-xs font-semibold text-ink/50 ring-1 ring-line">M</span></div>
                    <p class="mt-2 font-semibold text-ink">League median</p>
                    <p class="text-xs text-ink/55">Odd number of members: one plays the middle score</p>
                    <p class="mt-3 font-serif text-4xl font-bold text-ink/60 tabular-nums tracking-tight">{{ $card['median'] === null ? '–' : round($card['median'], 1) }}</p>
                    <p class="text-xs text-ink/55">line points{{ $card['medianPercent'] === null ? '' : ' · '.round($card['medianPercent'], 1).'% of plan' }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- The line meter: where each side sits against the 90%, 100%, and 150% marks. --}}
    <div class="mt-5" aria-hidden="true">
        <div class="relative">
            @foreach ($sides as $side)
                <div class="relative mb-1.5 h-3 overflow-hidden rounded-full bg-ivory ring-1 ring-line ring-inset">
                    <div class="absolute inset-y-0 left-0 rounded-full {{ $fill($side['result']) }}" style="width: {{ App\Services\MatchupBoard::meterPosition($side['share']) }}%"></div>
                </div>
            @endforeach
            @foreach ($marks as $mark)
                <div class="absolute -top-1 -bottom-1 w-px bg-ink/30" style="left: {{ App\Services\MatchupBoard::meterPosition($mark['share']) }}%"></div>
            @endforeach
        </div>
        <div class="relative mt-1 h-4 text-[0.6rem] text-ink/50">
            @foreach ($marks as $mark)
                <span class="absolute -translate-x-1/2 tabular-nums" style="left: {{ App\Services\MatchupBoard::meterPosition($mark['share']) }}%">{{ $mark['label'] }}</span>
            @endforeach
        </div>
    </div>
    <p class="sr-only">
        @foreach ($sides as $side)
            {{ $side['member']->user->name }}: {{ $side['percent'] === null ? 'no GoalWeek' : $side['percent'].' percent of plan' }}.
        @endforeach
    </p>

    {{-- Verdict and what it's worth. --}}
    @if ($card['home']['outcome']->isPhotoFinish() && ($card['away'] !== null || $card['median'] !== null))
        <p class="mt-3 flex justify-center"><span class="eyebrow rounded-full bg-gold/25 px-2.5 py-0.5 text-[0.6rem] text-ink" title="Same band, so the higher credited percent of plan takes it">Photo finish</span></p>
    @endif
    <p class="mt-3 rounded-xl bg-ivory px-4 py-3 text-center font-medium text-ink">{{ $card['verdict'] }}</p>
    <div class="mt-3 grid gap-2 text-xs text-ink/65 sm:grid-cols-2">
        @foreach ($sides as $side)
            <div @class(['rounded-lg border border-line px-3 py-2', 'sm:text-right' => $loop->index === 1])>
                <p class="font-semibold text-ink">{{ Str::before($side['member']->user->name, ' ') }}: {{ $final ? '' : 'if it ended now, ' }}+<span data-countup="{{ $side['total'] }}" data-key="total-{{ $side['member']->id }}">{{ $side['total'] }}</span> {{ Str::plural('pt', $side['total']) }}</p>
                <p>{{ $side['linePoints'] }} line + {{ $side['matchPoints'] }} match &middot; {{ $side['outcome']->label() }}</p>
                @if (! $final && $side['next'])
                    <p class="mt-0.5 text-club">{{ $side['next']['points'] }} more {{ Str::plural('point', $side['next']['points']) }} to {{ $side['next']['band']->label() }}</p>
                @elseif (! $final && $side['result'] === App\GoalWeekResult::Smashed)
                    <p class="mt-0.5 text-club">Top band. Keep the streak going.</p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($opponent && ! $final)
        <div class="mt-3 flex justify-end">
            <x-nudge-button :user="$opponent->user" />
        </div>
    @endif

    <x-matchup-timeline :league="$league" :week="$week" :matchup="$card['matchup']" :open="false" />
</article>

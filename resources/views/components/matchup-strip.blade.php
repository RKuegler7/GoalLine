{{-- The sticky scoreboard strip for one matchup page: both players side by side with their record, live percent of
     plan, band, projection on current pace and stars if the week ended now, and a small Live/Final pill with days left
     centred on the names row. It sits outside the swipeable pager (so it can stick while the page scrolls), and the one
     for the page in view shows. --}}
@props(['page', 'index'])

@php
    $sides = $page['sides'];
    $final = $page['final'];
    $card = $page['card'];
    $first = fn ($side) => $side['member']->team_name ?: Str::before($side['member']->user->name, ' ');
@endphp

<div data-pager-strip="{{ $index }}" @if ($index > 0) hidden @endif class="relative grid grid-cols-2 gap-x-3">
    <span @class(['absolute top-0.5 left-1/2 z-10 -translate-x-1/2 rounded-full px-1.5 py-0.5 text-[11px] font-semibold whitespace-nowrap', 'bg-ink/5 text-ink/70' => $final, 'bg-mint text-club' => ! $final])>
        {{ $final ? 'Final' : 'Live' }}@if (! $final && $page['daysLeft'] > 0) &middot; {{ $page['daysLeft'] }}d left @endif
    </span>

    @foreach ($sides as $sideIndex => $side)
        @php $right = $sideIndex === 1; @endphp
        <div @class(['min-w-0', 'text-right' => $right])>
            <p @class(['flex max-w-[calc(100%-2.75rem)] items-center gap-1.5', 'ml-auto flex-row-reverse' => $right])>
                <x-avatar :user="$side['member']->user" class="size-6 shrink-0 text-[11px]" />
                <span class="truncate text-[13px] font-semibold text-ink">{{ $first($side) }}</span>
                <span class="shrink-0 text-[11px] text-ink/70 tabular-nums" title="Wins, draws and losses this season">{{ $side['record']['wins'] }}&ndash;{{ $side['record']['ties'] }}&ndash;{{ $side['record']['losses'] }}</span>
            </p>
            <p @class(['mt-1 flex items-center gap-1.5', 'flex-row-reverse' => $right])>
                <span class="shrink-0 text-[20px] leading-none font-bold text-club tabular-nums">{{ $side['percentLabel'] ?? '–' }}</span>
                @if ($side['result'])
                    <x-goalweek-result :result="$side['result']" :not-started="$side['notStarted']" compact class="min-w-0" />
                @endif
            </p>
            <p class="mt-0.5 truncate text-[11px] text-ink/70">
                @if (! $final && $side['projection']['band'])
                    <span title="Projected, on current pace">Proj. <span class="font-semibold text-ink">{{ $side['projection']['band']->label() }}</span></span> &middot;
                @endif
                <span class="font-semibold text-ink">+{{ $side['total'] }}</span> {{ Str::plural('star', $side['total']) }}{{ $final ? '' : ' if it ended now' }}
            </p>
        </div>
    @endforeach

    @if ($page['kind'] === 'bye')
        <div class="min-w-0 text-right">
            <p class="ml-auto max-w-[calc(100%-2.75rem)] truncate text-[13px] font-semibold text-ink">Bye week</p>
            <p class="mt-1 text-[20px] leading-none font-bold text-ink/70 tabular-nums">{{ $card['median'] === null ? '–' : round($card['median'], 1) }}</p>
            <p class="mt-0.5 truncate text-[11px] text-ink/70">line stars only</p>
        </div>
    @endif
</div>

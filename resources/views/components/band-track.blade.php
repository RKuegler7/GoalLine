{{-- A GoalWeek's live band: percent of plan, the band chip, line points if the week ended now, the projected band,
     and a track marked at the band lines (In line, Past the line, Smashed) with a marker at the percent. Every value
     comes from the scoring code on the server; after a check-in the page's fresh copy swaps in and the fill and
     marker glide from the old value to the new one (live.js). The browser never works out a score.
     compact: just the track and the chip, for tight rows. --}}
@props(['week', 'compact' => false, 'label' => null])

@php
    $result = $week->result();
    $percent = $week->creditedPercent();
    $max = (int) round(App\GoalWeekResult::SMASHED_AT * 100) + 25;
    $position = fn (float $value) => round(min(max($value, 0), $max) / $max * 100, 2);
    $ticks = [
        // 90% and 100% sit close together, so their labels hang off opposite sides of the tick instead of overlapping.
        ['value' => (int) round(App\GoalWeekResult::IN_LINE_AT * 100), 'label' => 'In line', 'align' => '-translate-x-full pr-0.5'],
        ['value' => (int) round(App\GoalWeekResult::PAST_ABOVE * 100), 'label' => 'Past', 'align' => 'pl-0.5'],
        ['value' => (int) round(App\GoalWeekResult::SMASHED_AT * 100), 'label' => 'Smashed', 'align' => '-translate-x-1/2'],
    ];
    $fill = match ($result) {
        App\GoalWeekResult::InLine => 'bg-grass',
        App\GoalWeekResult::Past => 'bg-gradient-to-r from-grass to-gold',
        App\GoalWeekResult::Smashed => 'bg-gradient-to-r from-grass via-gold to-sky-700',
        default => 'bg-ink/30',
    };
    $projection = $compact ? null : App\Services\WeekPace::project($week);
    $notStarted = $week->isNotStarted();
@endphp

<div data-band-track data-week="{{ $week->id }}" data-band="{{ $result?->value }}" data-percent="{{ $percent }}" {{ $attributes->class('min-w-0') }}>
    @unless ($compact)
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            @if ($label)
                <span class="min-w-0 truncate text-[13px] font-semibold text-ink">{{ $label }}</span>
            @endif
            <span class="text-[17px] leading-none font-bold text-club tabular-nums"><span data-countup="{{ $percent }}" data-key="band-pct-{{ $week->id }}">{{ $percent }}</span>%</span>
            @if ($result)
                <x-goalweek-result :result="$result" :close-call="$week->isCloseCall()" :not-started="$notStarted" :needs-proof="$week->needsProofForSmashed()" compact />
            @endif
            <span class="text-[11px] text-ink/70">+{{ $result?->points() ?? 0 }} line {{ Str::plural('pt', $result?->points() ?? 0) }} if it ended now</span>
            @if ($projection && $projection['band'])
                <span class="text-[11px] text-ink/70">&middot; Projected <span class="font-semibold text-ink">{{ $projection['band']->label() }}</span></span>
            @endif
        </div>
    @endunless

    {{-- The track: the fill and marker show the percent; ticks mark where each band starts. --}}
    <div @class(['relative', 'mt-2 mb-6' => ! $compact, 'mt-1.5' => $compact])>
        <div class="relative h-2 rounded-full bg-ink/10" role="img" aria-label="{{ $percent }}% of plan{{ $result ? ', '.$result->label() : '' }}">
            <div data-progress-bar data-key="band-fill-{{ $week->id }}" class="{{ $fill }} relative h-full rounded-full" style="width: {{ $position($percent) }}%">
                <span class="absolute top-1/2 right-0 size-3.5 translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-club shadow-sm" data-band-marker aria-hidden="true"></span>
            </div>
            @foreach ($ticks as $tick)
                <span class="absolute top-1/2 h-3.5 w-px -translate-y-1/2 bg-ink/40" style="left: {{ $position($tick['value']) }}%" aria-hidden="true"></span>
            @endforeach
        </div>
        @unless ($compact)
            @foreach ($ticks as $tick)
                <span class="{{ $tick['align'] }} absolute top-3 text-[11px] font-semibold whitespace-nowrap text-ink/70 tabular-nums" style="left: {{ $position($tick['value']) }}%" aria-hidden="true">{{ $tick['value'] }}%</span>
            @endforeach
        @endunless
    </div>
</div>

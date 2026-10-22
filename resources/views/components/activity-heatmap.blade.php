{{-- A year of check-ins: one square per day, darker green for bigger days. Scrolls sideways on phones. --}}
@props(['heatmap'])

@php
    $shades = ['bg-line/70', 'bg-grass/30', 'bg-grass/55', 'bg-grass/80', 'bg-club'];
    $weeks = $heatmap->weeks();
@endphp

<section {{ $attributes->class('rounded-2xl border border-line bg-white px-5 py-4') }}>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="eyebrow text-club">A year of check-ins</h2>
        <p class="text-sm text-ink/55">
            {{ number_format($heatmap->total()) }} {{ Str::plural('check-in', $heatmap->total()) }} &middot;
            {{ $heatmap->activeDays() }} active {{ Str::plural('day', $heatmap->activeDays()) }} &middot;
            longest streak {{ $heatmap->longestStreak() }} {{ Str::plural('day', $heatmap->longestStreak()) }}
        </p>
    </div>

    <div class="-mx-5 mt-3 overflow-x-auto px-5 pb-1" data-scroll-end>
        <div class="flex w-max gap-[3px]" role="img" aria-label="{{ $heatmap->total() }} check-ins on {{ $heatmap->activeDays() }} days in the last year">
            @foreach ($weeks as $week)
                <div class="flex flex-col gap-[3px]">
                    @foreach ($week as $day)
                        @if ($day)
                            <span @class(['size-[11px] rounded-[3px]', $shades[$day['level']], 'ring-1 ring-club ring-offset-1' => $day['date']->isSameDay($heatmap->today)])
                                title="{{ $day['date']->format('D, M j, Y') }}: {{ $day['count'] }} {{ Str::plural('check-in', $day['count']) }}"></span>
                        @else
                            <span class="size-[11px]"></span>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-2 flex items-center justify-end gap-1 text-[0.65rem] text-ink/45">
        Less
        @foreach ($shades as $shade)
            <span class="size-[11px] rounded-[3px] {{ $shade }}"></span>
        @endforeach
        More
    </div>
</section>

{{-- A habit's current week as a row of dots, one per day: filled when it was done. Long stretches show just this calendar week. --}}
@props(['habit', 'week'])

@php
    $today = App\Clock::today();
    $from = $week->starts_on->max($today->copy()->startOfWeek(Carbon\CarbonInterface::MONDAY))->copy();
    $to = $week->ends_on->min($from->copy()->endOfWeek(Carbon\CarbonInterface::SUNDAY))->copy()->startOfDay();
@endphp

<span {{ $attributes->class('flex shrink-0 items-center gap-1') }} title="{{ $habit->doneIn($week) }} of {{ $habit->targetFor($week) }} this GoalWeek">
    @for ($day = $from; $day->lte($to); $day = $day->copy()->addDay())
        <span @class([
            'size-2 rounded-full',
            'bg-grass' => $habit->isDoneOn($day),
            'bg-line' => ! $habit->isDoneOn($day) && $day->lte($today),
            'bg-line/50' => ! $habit->isDoneOn($day) && $day->gt($today),
            'ring-2 ring-club/30 ring-offset-1' => $day->isSameDay($today),
        ])></span>
    @endfor
    <span class="ml-1 text-xs text-ink/55 tabular-nums">{{ $habit->doneIn($week) }}/{{ $habit->targetFor($week) }}</span>
</span>

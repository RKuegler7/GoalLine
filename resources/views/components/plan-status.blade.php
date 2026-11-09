{{-- A GoalWeek's plan status for the league: "At minimum pace", "Above minimum pace", or "Lighter week". --}}
@props(['week', 'href' => null])

@php
    $status = $week?->planStatus();
    $label = App\Models\RoadmapWeek::planStatusLabel($status);
    $classes = match ($status) {
        App\Models\RoadmapWeek::ABOVE_MINIMUM => 'bg-mint text-club',
        App\Models\RoadmapWeek::LIGHTER => 'bg-gold/20 text-ink',
        default => 'bg-ivory text-ink/70 ring-1 ring-inset ring-line',
    };
@endphp

@if ($label)
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->class("inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold hover:underline {$classes}") }} title="Planned {{ $week->plannedPoints() }} · minimum {{ $week->effectiveFloor() }}">{{ $label }}</a>
    @else
        <span {{ $attributes->class("inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {$classes}") }} title="Planned {{ $week->plannedPoints() }} · minimum {{ $week->effectiveFloor() }}">{{ $label }}</span>
    @endif
@endif

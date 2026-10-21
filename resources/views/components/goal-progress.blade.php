{{--
    A goal's real progress, drawn in the same language as the logo's signature Line.
    Driven by data: pass a percentage from 0 to 100.
--}}
@props(['percent' => 0, 'label' => 'Goal progress'])

@php
    $percent = max(0, min(100, (float) $percent));
@endphp

<div {{ $attributes->class('relative h-4') }} role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($percent) }}">
    <div class="absolute inset-x-0 top-1/2 h-[3px] -translate-y-1/2 rounded-full bg-grass/25"></div>
    <div class="absolute top-1/2 left-0 h-[3px] -translate-y-1/2 rounded-full bg-grass" style="width: {{ $percent }}%"></div>
    <div class="absolute top-0 right-0 h-4 w-[3px] rounded-full bg-grass"></div>
    <div class="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-mint bg-grass" style="left: {{ $percent }}%"></div>
</div>

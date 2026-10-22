{{-- The daily streak chip in the Clubhouse hero: lit once today has a check-in, a warning while it's at risk. --}}
@props(['streak'])

@php
    $days = $streak->current();
    $today = $streak->checkedInToday();
@endphp

<a href="#gameplan" {{ $attributes->class([
    'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
    'bg-gold text-ink hover:brightness-105' => $today,
    'bg-ivory/10 text-ivory ring-1 ring-gold/70 ring-inset hover:bg-ivory/15' => ! $today && $days > 0,
    'bg-ivory/10 text-ivory/85 ring-1 ring-ivory/20 ring-inset hover:bg-ivory/15' => $days === 0,
]) }}>
    <x-icon name="flame" :class="$today ? 'size-4 text-ink' : 'size-4 text-gold'" />
    @if ($days === 0)
        Check in today to start a streak
    @elseif ($today)
        {{ $days }}-day streak
    @else
        {{ $days }}-day streak &middot; check in today to keep it
    @endif
</a>

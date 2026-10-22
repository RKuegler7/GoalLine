{{-- Log a habit for a day, or undo it. A rounded square, to tell habits apart from one-off tasks. --}}
@props(['habit', 'day', 'disabled' => false, 'return' => null])

@php
    $done = $habit->isDoneOn($day);
@endphp

@if ($disabled)
    <span @class([
        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border-2',
        'border-grass bg-grass text-white' => $done,
        'border-club/25' => ! $done,
    ]) aria-hidden="true">
        @if ($done)
            <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3.5 8.5l3 3 6-7" /></svg>
        @endif
    </span>
@else
    <form method="POST" action="{{ route('habits.log', $habit) }}">
        @csrf
        <input type="hidden" name="date" value="{{ $day->toDateString() }}">
        @if ($return)
            <input type="hidden" name="return" value="{{ $return }}">
        @endif
        <button type="submit" title="{{ $done ? 'Undo' : 'Log it for '.($day->isSameDay(App\Clock::today()) ? 'today' : $day->format('l')) }}" @class([
            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition',
            'border-grass bg-grass text-white' => $done,
            'border-club/40 hover:border-club hover:bg-mint' => ! $done,
        ])>
            @if ($done)
                <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
            @endif
            <span class="sr-only">{{ $done ? 'Done: ' : 'Log: ' }}{{ $habit->title }}, {{ $day->format('l') }}</span>
        </button>
    </form>
@endif

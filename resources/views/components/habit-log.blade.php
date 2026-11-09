{{-- Log a habit for a day: one tap for a check-off, or quick number entry for a number or minutes habit. Big tap
     targets on phones. A number under the threshold is saved but doesn't count yet, and says so calmly. --}}
@props(['habit', 'day', 'return' => null, 'compact' => false])

@php
    $log = $habit->logOn($day);
    $done = (bool) $log?->met;
    $dayName = $day->isSameDay(App\Clock::today()) ? 'today' : $day->format('l');
    $check = '<svg viewBox="0 0 16 16" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>';
@endphp

@if (! $habit->takesNumber())
    <form method="POST" action="{{ route('habits.log', $habit) }}" data-live data-optimistic="check" data-celebrate="check" data-live-key="habit-{{ $habit->id }}-{{ $day->toDateString() }}">
        @csrf
        <input type="hidden" name="date" value="{{ $day->toDateString() }}">
        <input type="hidden" name="intent" value="{{ $done ? 'open' : 'done' }}">
        @if ($return)
            <input type="hidden" name="return" value="{{ $return }}">
        @endif
        <button type="submit" data-check="{{ $done ? 'done' : 'open' }}" data-focus-key="habit-{{ $habit->id }}-{{ $day->toDateString() }}" title="{{ $done ? 'Undo' : 'Done '.$dayName }}" @class([
            'flex shrink-0 items-center justify-center rounded-lg border-2 transition',
            'size-9 sm:size-7' => ! $compact,
            'size-9' => $compact,
            'border-grass bg-grass text-ink' => $done,
            'border-club/40 hover:border-club hover:bg-mint' => ! $done,
        ])>
            @if ($done)
                {!! $check !!}
            @endif
            <span class="sr-only">{{ $done ? 'Done, undo: ' : 'Mark done: ' }}{{ $habit->title }}, {{ $dayName }}</span>
        </button>
    </form>
@else
    <details @class(['relative', 'shrink-0' => $compact])>
        <summary @class([
            'flex cursor-pointer list-none items-center justify-center rounded-lg border-2 text-xs font-semibold tabular-nums transition [&::-webkit-details-marker]:hidden',
            'size-9' => $compact,
            'h-9 min-w-9 px-2 sm:h-7' => ! $compact,
            'border-grass bg-grass text-ink' => $done,
            'border-gold bg-gold/15 text-ink' => $log && ! $done,
            'border-club/40 text-club hover:border-club hover:bg-mint' => ! $log,
        ]) title="{{ $log ? $habit->formatNumber($log->value ?? 0).' '.$habit->unitLabel() : 'Log '.$dayName }}">
            @if ($done && $compact)
                {!! $check !!}
            @elseif ($log)
                {{ $habit->formatNumber($log->value ?? 0) }}
            @else
                +
            @endif
            <span class="sr-only">{{ $habit->title }}, {{ $dayName }}: {{ $log ? 'logged' : 'log a number' }}</span>
        </summary>
        <div class="absolute right-0 z-20 mt-1 w-60 rounded-xl border border-line bg-white p-3 shadow-lg">
            <form method="POST" action="{{ route('habits.log', $habit) }}" data-live class="flex items-center gap-1.5">
                @csrf
                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                @if ($return)
                    <input type="hidden" name="return" value="{{ $return }}">
                @endif
                <label for="habit-{{ $habit->id }}-{{ $day->toDateString() }}" class="sr-only">{{ $habit->title }}, {{ $dayName }}</label>
                <input id="habit-{{ $habit->id }}-{{ $day->toDateString() }}" name="value" type="number" inputmode="decimal" step="any" min="0" required
                    value="{{ $log?->value !== null ? $habit->formatNumber($log->value) : '' }}" placeholder="{{ $habit->formatNumber((float) $habit->threshold) }}"
                    class="w-20 rounded-lg border border-line px-2 py-1.5 text-sm tabular-nums outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                <span class="text-xs text-ink/70">{{ $habit->unitLabel() }}</span>
                <button type="submit" class="ml-auto rounded-lg bg-club px-3 py-1.5 text-xs font-semibold text-ivory hover:bg-club-dark">{{ $log ? 'Update' : 'Log' }}</button>
            </form>
            <p class="mt-1.5 text-xs text-ink/70">Counts at {{ $habit->ruleLabel() }}.</p>
            @if ($log)
                <form method="POST" action="{{ route('habits.log', $habit) }}" data-live class="mt-1">
                    @csrf
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    @if ($return)
                        <input type="hidden" name="return" value="{{ $return }}">
                    @endif
                    <button type="submit" class="text-xs font-semibold text-ink/70 hover:text-ink">Clear this day</button>
                </form>
            @endif
        </div>
    </details>
@endif

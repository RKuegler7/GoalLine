{{-- The check circle for a task. A task that tracks a number is logged from its details instead (x-task-details),
     so here it shows an open dashed circle until it's done. --}}
@props(['task', 'checkable' => true, 'return' => null, 'size' => 'size-5'])

@php
    $done = (bool) $task->completed_at;
    // A later day's task stays Upcoming (locked) until its day, even in an open GoalWeek.
    $future = ! $done && $task->planned_on !== null && $task->planned_on->toDateString() > App\Clock::today()->toDateString();
    $tick = '<svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>';
@endphp

@if ($checkable && ! $future && ($done || ! $task->result_required))
    <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}" data-live data-optimistic="check" data-celebrate="check" data-live-key="task-{{ $task->id }}">
        @csrf
        {{-- The state this tap asks for, so a double tap can't undo itself. --}}
        <input type="hidden" name="intent" value="{{ $done ? 'open' : 'done' }}">
        @if ($return)
            <input type="hidden" name="return" value="{{ $return }}">
        @endif
        <button type="submit" data-check="{{ $done ? 'done' : 'open' }}" data-focus-key="task-{{ $task->id }}" title="{{ $done ? 'Mark as not done' : 'Check off' }}" @class([
            "mt-0.5 flex {$size} shrink-0 items-center justify-center rounded-full border-2 transition",
            'border-grass bg-grass text-ink' => $done,
            'border-grass/60 hover:border-grass hover:bg-mint' => ! $done,
        ])>
            @if ($done)
                {!! $tick !!}
            @endif
            <span class="sr-only">{{ $done ? 'Done: ' : 'Check off: ' }}{{ $task->description }}</span>
        </button>
    </form>
@else
    @php $opensOn = ! $done && $task->relationLoaded('week') ? $task->week->checkInOpensOn($task) : null; @endphp
    @if ($opensOn)
        {{-- Not yet: before kickoff, or a later day's task. Tapping explains why. --}}
        <button type="button" data-open-sheet="kickoff-sheet" data-opens-on="{{ $opensOn->format('D, M j') }}" title="Opens {{ $opensOn->format('D, M j') }}"
            class="mt-0.5 flex {{ $size }} shrink-0 items-center justify-center rounded-full border-2 border-line bg-ivory text-ink/60" data-check="locked">
            <svg viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.5" /><path d="M5.5 7V5a2.5 2.5 0 015 0v2" /></svg>
            <span class="sr-only">Upcoming: {{ $task->description }}. Opens {{ $opensOn->format('D, M j') }}.</span>
        </button>
    @else
    <span @class([
        "mt-0.5 flex {$size} shrink-0 items-center justify-center rounded-full border-2",
        'border-grass bg-grass text-ink' => $done,
        'border-dashed border-grass/60' => ! $done && $task->result_required,
        'border-grass/40' => ! $done && ! $task->result_required,
    ]) aria-hidden="true">
        @if ($done)
            {!! $tick !!}
        @endif
    </span>
    @endif
@endif

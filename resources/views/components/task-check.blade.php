{{-- The check circle for a task. A task that tracks a number is logged from its details instead (x-task-details),
     so here it shows an open dashed circle until it's done. --}}
@props(['task', 'checkable' => true, 'return' => null, 'size' => 'size-5'])

@php
    $done = (bool) $task->completed_at;
    $tick = '<svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>';
@endphp

@if ($checkable && ($done || ! $task->result_required))
    <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}">
        @csrf
        @if ($return)
            <input type="hidden" name="return" value="{{ $return }}">
        @endif
        <button type="submit" title="{{ $done ? 'Mark as not done' : 'Check off' }}" @class([
            "mt-0.5 flex {$size} shrink-0 items-center justify-center rounded-full border-2 transition",
            'border-grass bg-grass text-white' => $done,
            'border-grass/60 hover:border-grass hover:bg-mint' => ! $done,
        ])>
            @if ($done)
                {!! $tick !!}
            @endif
            <span class="sr-only">{{ $done ? 'Done: ' : 'Check off: ' }}{{ $task->description }}</span>
        </button>
    </form>
@else
    <span @class([
        "mt-0.5 flex {$size} shrink-0 items-center justify-center rounded-full border-2",
        'border-grass bg-grass text-white' => $done,
        'border-dashed border-grass/60' => ! $done && $task->result_required,
        'border-grass/40' => ! $done && ! $task->result_required,
    ]) aria-hidden="true">
        @if ($done)
            {!! $tick !!}
        @endif
    </span>
@endif

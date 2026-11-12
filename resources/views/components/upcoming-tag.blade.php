{{-- "Upcoming · Opens Mon, Nov 16": a task that can't be checked in yet (before kickoff, or a later day's task). --}}
@props(['task'])

@php $opensOn = ! $task->completed_at ? $task->week->checkInOpensOn($task) : null; @endphp
@if ($opensOn)
    <button type="button" data-open-sheet="kickoff-sheet" data-upcoming
        class="ml-1 inline-flex items-center gap-1 rounded-full bg-ivory px-2 py-0.5 align-middle text-[11px] font-semibold text-ink/70 ring-1 ring-line">
        <svg viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.5" /><path d="M5.5 7V5a2.5 2.5 0 015 0v2" /></svg>
        Upcoming &middot; Opens {{ $opensOn->format('D, M j') }}
    </button>
@endif

{{-- Small labels on a task: a group task the league voted in, or a personal task with who added it, so nobody can
     quietly inflate a plan. --}}
@props(['task'])

@if ($task->isGroupTask())
    <span class="inline-flex items-center gap-1 rounded-full bg-club/10 px-2 py-0.5 text-[0.65rem] font-semibold text-club" title="Voted in by the GoalLeague">
        <svg viewBox="0 0 20 20" class="size-3" fill="currentColor" aria-hidden="true"><path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1 16c0-3 2.7-5 6-5s6 2 6 5v1H1v-1Zm13 1v-1c0-1.7-.6-3.1-1.6-4.2.5-.1 1-.1 1.6-.1 2.8 0 5 1.7 5 4.3v1h-5Z" /></svg>
        Group task
    </span>
@elseif ($task->is_personal)
    @php($owner = $task->week->goal->user)
    <span class="rounded-full bg-gold/20 px-2 py-0.5 text-[0.65rem] font-semibold text-ink/75">Added by {{ $owner->is(auth()->user()) ? 'you' : $owner->name }}</span>
@endif

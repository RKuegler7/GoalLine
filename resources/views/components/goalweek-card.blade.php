@props(['week', 'weeksPerStretch', 'canEdit'])

@php
    $current = $week->isCurrent();
    $past = $week->isPast();
    $editable = $canEdit && ! $past;
    $checkable = $canEdit && $week->isCheckable();
    $result = $week->result();
    $closeCall = $week->isCloseCall();
    $notStarted = $current && $week->isNotStarted();
    $short = $result === App\GoalWeekResult::Short && ! $notStarted;

    $habits = $week->habits();
    $units = $week->plannedUnits();
    $plannedLeft = $units - $week->doneUnits();
    $toInLine = max(0, (int) ceil($units * App\GoalWeekResult::IN_LINE_AT) - $week->doneUnits());
    $bonusNeeded = max(1, (int) ceil($units * App\GoalWeekResult::SMASH_BONUS_SHARE)) - $week->tasks->filter->is_bonus->filter->completed_at->count();
    $checkIns = fn (int $count) => $count.' more '.($habits->isEmpty() ? Str::plural('task', $count) : Str::plural('check-in', $count));

    $nudge = match (true) {
        $notStarted => 'New GoalWeek. Check off your first task to get going.',
        $result === App\GoalWeekResult::Short => 'Check off '.$checkIns($toInLine).' to get in line.',
        $closeCall => 'Close call: '.$checkIns($week->tasksToSafety()).' gets you safely in line.',
        $result === App\GoalWeekResult::InLine && $plannedLeft > 0 => 'Finish '.$checkIns($plannedLeft).' and hit your milestone to get past the line.',
        $result === App\GoalWeekResult::InLine => 'Hit your milestone to get past the line.',
        $result === App\GoalWeekResult::Past => 'Past the line! Add and finish '.$bonusNeeded.' bonus '.Str::plural('task', $bonusNeeded).' to smash it.',
        $result === App\GoalWeekResult::Smashed => 'You smashed the line this GoalWeek.',
        default => null,
    };
@endphp

<li id="goalweek-{{ $week->position }}" @class([
    'scroll-mt-6 rounded-2xl border bg-white px-6 py-5',
    'border-club ring-4 ring-mint' => $current,
    'border-line' => ! $current,
])>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span @class([
                'eyebrow rounded-full px-3 py-1 text-[0.68rem] tabular-nums',
                'bg-club text-ivory' => $current,
                'bg-mint text-club' => ! $current,
            ])>{{ $week->label() }}</span>
            <span class="text-sm text-ink/60">
                {{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format($week->starts_on->month === $week->ends_on->month ? 'j' : 'M j') }}
            </span>
            @if ($current)
                <span class="eyebrow rounded-full bg-grass px-2.5 py-1 text-[0.65rem] text-white">You are here</span>
            @endif
        </div>

        @if ($result && ($past || ! $current))
            <div class="flex items-center gap-2">
                <x-goalweek-result :result="$result" :close-call="$closeCall" />
                @if ($week->goal->user_id === auth()->id() && in_array($result, [App\GoalWeekResult::Past, App\GoalWeekResult::Smashed], true))
                    <a href="{{ route('highlights.create', ['week' => $week]) }}" class="text-xs font-semibold text-club hover:underline">Share</a>
                @endif
                <span class="text-xs text-ink/50">{{ $week->isLocked() ? 'Final' : 'Check-ins close '.$week->ends_on->copy()->addDays(App\Models\RoadmapWeek::CHECK_IN_GRACE_DAYS)->format('M j') }}</span>
            </div>
        @endif
    </div>

    <h3 @class(['mt-3 font-serif text-xl font-medium', 'text-club' => ! $past, 'text-club/70' => $past])>{{ $week->focus }}</h3>

    <div class="mt-1 flex items-start gap-3">
        @if ($checkable)
            <form method="POST" action="{{ route('goalweeks.milestone', $week) }}">
                @csrf
                <button type="submit" title="{{ $week->milestone_hit_at ? 'Mark milestone as not hit' : 'Mark milestone as hit' }}" @class([
                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition',
                    'border-grass bg-grass text-white' => $week->milestone_hit_at,
                    'border-club/40 hover:border-club' => ! $week->milestone_hit_at,
                ])>
                    @if ($week->milestone_hit_at)
                        <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                    @endif
                    <span class="sr-only">{{ $week->milestone_hit_at ? 'Milestone hit' : 'Mark milestone hit' }}</span>
                </button>
            </form>
        @endif
        <p class="text-ink/75">
            <span class="font-semibold text-ink">Milestone:</span> {{ $week->milestone }}
            @if ($week->milestone_hit_at)
                <span class="ml-1 text-sm font-semibold text-grass">Hit</span>
                @if ($week->goal->user_id === auth()->id())
                    <a href="{{ route('highlights.create', ['milestone' => $week]) }}" class="ml-1 text-sm font-semibold text-club hover:underline">Share</a>
                @endif
            @endif
        </p>
    </div>

    @if ($canEdit && ($week->review || $week->isReviewable()))
        <a href="{{ route('goalweeks.review', $week) }}" @class([
            'mt-4 flex items-center justify-between gap-3 rounded-xl px-4 py-2.5 text-sm transition',
            'bg-mint/60 hover:bg-mint' => $week->review,
            'bg-gold/20 hover:bg-gold/30' => ! $week->review,
        ])>
            <span class="min-w-0">
                @if ($week->review?->coach_tip)
                    <span class="font-semibold text-club">Coach&rsquo;s tip:</span> <span class="text-ink/80">{{ Str::limit($week->review->coach_tip, 160) }}</span>
                @elseif ($week->review)
                    <span class="font-semibold text-ink">Reviewed.</span> <span class="text-ink/70">Your coach&rsquo;s tip is on its way.</span>
                @else
                    <span class="font-semibold text-ink">Wrap up this GoalWeek.</span> <span class="text-ink/70">A 2-minute review gets you a coach&rsquo;s tip.</span>
                @endif
            </span>
            <span class="shrink-0 font-semibold text-ink">{{ $week->review ? 'Open' : 'Review' }} &rarr;</span>
        </a>
    @endif

    @if ($current && $result)
        <div @class([
            'mt-4 rounded-xl px-4 py-3',
            'bg-amber-50' => $closeCall,
            'bg-red-50' => $short,
            'bg-ivory' => ! $closeCall && ! $short,
        ])>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-semibold text-ink">This GoalWeek so far</span>
                <x-goalweek-result :result="$result" :close-call="$closeCall" :not-started="$notStarted" />
            </div>
            <x-goal-progress class="mt-3" :percent="$week->completionShare() * 100" label="Planned work done this GoalWeek" />
            @if ($nudge)
                <p @class(['mt-2 text-sm', 'text-amber-800' => $closeCall, 'text-red-700' => $short, 'text-ink/70' => ! $closeCall && ! $short])>
                    {{ $nudge }}
                    @if (in_array($result, [App\GoalWeekResult::Past, App\GoalWeekResult::Smashed], true))
                        <a href="{{ route('highlights.create', ['week' => $week]) }}" class="ml-1 font-semibold text-club hover:underline">Share this GoalWeek</a>
                    @endif
                </p>
            @endif
        </div>
    @endif

    <ul class="mt-4 space-y-2 border-t border-line pt-4">
        @foreach ($week->tasks as $task)
            <li class="group flex items-start gap-3 text-ink/85">
                @if ($checkable)
                    <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}">
                        @csrf
                        <button type="submit" title="{{ $task->completed_at ? 'Mark as not done' : 'Check off' }}" @class([
                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition',
                            'border-grass bg-grass text-white' => $task->completed_at,
                            'border-grass/60 hover:border-grass hover:bg-mint' => ! $task->completed_at,
                        ])>
                            @if ($task->completed_at)
                                <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                            @endif
                            <span class="sr-only">{{ $task->completed_at ? 'Done: '.$task->description : 'Check off: '.$task->description }}</span>
                        </button>
                    </form>
                @else
                    <span @class([
                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                        'border-grass bg-grass text-white' => $task->completed_at,
                        'border-grass/40' => ! $task->completed_at,
                    ]) aria-hidden="true">
                        @if ($task->completed_at)
                            <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3.5 8.5l3 3 6-7" /></svg>
                        @endif
                    </span>
                @endif

                @if ($editable)
                    <details class="min-w-0 flex-1">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-3 [&::-webkit-details-marker]:hidden">
                            <span @class(['line-through decoration-ink/30 text-ink/55' => $task->completed_at])>
                                {{ $task->description }}
                                @if ($task->is_bonus)
                                    <span class="eyebrow ml-1 rounded-full bg-gold/25 px-2 py-0.5 text-[0.6rem] text-ink no-underline">Bonus</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-xs font-semibold text-club opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">Edit</span>
                        </summary>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <form method="POST" action="{{ route('roadmap.tasks.update', $task) }}" class="flex flex-1 gap-2">
                                @csrf
                                @method('PUT')
                                <label for="task-{{ $task->id }}" class="sr-only">Task</label>
                                <input id="task-{{ $task->id }}" name="description" value="{{ $task->description }}" maxlength="250" required
                                    class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-1.5 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                                <button type="submit" class="rounded-lg bg-club px-3 py-1.5 text-sm font-semibold text-ivory hover:bg-club-dark">Save</button>
                            </form>
                            <form method="POST" action="{{ route('roadmap.tasks.destroy', $task) }}"
                                onsubmit="return confirm('Remove this task? Your coach will check you\'re still in line.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">Remove</button>
                            </form>
                        </div>
                    </details>
                @else
                    <span @class(['line-through decoration-ink/30 text-ink/55' => $task->completed_at])>
                        {{ $task->description }}
                        @if ($task->is_bonus)
                            <span class="eyebrow ml-1 rounded-full bg-gold/25 px-2 py-0.5 text-[0.6rem] text-ink">Bonus</span>
                        @endif
                    </span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($habits->isNotEmpty() && $week->isStarted())
        <ul class="mt-3 space-y-2 border-t border-line pt-3">
            @foreach ($habits as $habit)
                <li class="flex items-center gap-3 text-ink/85">
                    <x-habit-toggle :habit="$habit" :day="App\Clock::today()" :disabled="! $checkable || ! $current || $habit->archived_at" />
                    <span class="min-w-0 flex-1">
                        <x-icon name="repeat" class="inline size-3.5 text-club/60" /> {{ $habit->title }}
                    </span>
                    @if ($current)
                        <x-habit-week :habit="$habit" :week="$week" />
                    @else
                        <span class="text-xs text-ink/55 tabular-nums">{{ $habit->doneIn($week) }}/{{ $habit->targetFor($week) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($editable)
        <form method="POST" action="{{ route('roadmap.tasks.store', $week) }}" class="mt-3 flex gap-2">
            @csrf
            <label for="new-task-{{ $week->id }}" class="sr-only">Add a task to {{ $week->label() }}</label>
            <input id="new-task-{{ $week->id }}" name="description" maxlength="250" required
                placeholder="{{ $current ? 'Add a bonus task' : 'Add your own task' }}"
                class="min-w-0 flex-1 rounded-lg border border-dashed border-line bg-ivory/60 px-3 py-1.5 text-sm outline-none placeholder:text-ink/40 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10">
            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-club hover:bg-mint">Add</button>
        </form>
    @endif
</li>

@props(['goal'])

@php
    $status = $goal->roadmap_status;
    $weeksPerStretch = $goal->weeksPerStretch();
    $canEdit = $goal->isActive();
    $replanFrom = $goal->replanFromPosition();
@endphp

@if ($status === App\RoadmapStatus::Ready)
    <section class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <h2 class="eyebrow text-club">Your roadmap</h2>
            @if ($canEdit && $replanFrom)
                <form method="POST" action="{{ route('goals.roadmap.store', $goal) }}"
                    onsubmit="return confirm('{{ $replanFrom > 1 ? 'Re-plan from this GoalWeek on? Earlier GoalWeeks stay as they are; the rest, including your task edits, are rebuilt.' : 'Build a fresh roadmap? This replaces the current one, including your task edits.' }}')">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-club hover:underline">
                        {{ $replanFrom > 1 ? 'Re-plan from here' : 'Rebuild roadmap' }}
                    </button>
                </form>
            @endif
        </div>

        <div class="mt-3 rounded-2xl border border-line bg-white px-6 py-5">
            <p class="font-serif text-xl leading-relaxed text-ink">{{ $goal->roadmap_summary }}</p>
            <p class="mt-3 border-t border-line pt-3 text-sm text-ink/60">
                @if ($weeksPerStretch > 1)
                    This is a longer goal, so each step of your plan spans <span class="font-semibold text-club">{{ $weeksPerStretch }} GoalWeeks</span>, with one milestone to hit before the next begins.
                @else
                    Your plan runs one <span class="font-semibold text-club">GoalWeek</span> at a time, with one milestone to hit each week.
                @endif
                @if ($canEdit)
                    Make it yours: add, reword, or remove tasks, and your coach will check you're still in line.
                @endif
            </p>
        </div>

        <x-coach-check :goal="$goal" />

        <ol class="mt-4 space-y-3">
            @foreach ($goal->weeks as $week)
                @php
                    $current = $week->isCurrent();
                    $past = $week->isPast();
                    $editable = $canEdit && ! $past;
                @endphp
                <li id="goalweek-{{ $week->position }}" @class([
                    'scroll-mt-6 rounded-2xl border bg-white px-6 py-5',
                    'border-club ring-4 ring-mint' => $current,
                    'border-line' => ! $current,
                    'opacity-60' => $past,
                ])>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span @class([
                            'eyebrow rounded-full px-3 py-1 text-[0.68rem] tabular-nums',
                            'bg-club text-ivory' => $current,
                            'bg-mint text-club' => ! $current,
                        ])>{{ $week->label($weeksPerStretch) }}</span>
                        <span class="text-sm text-ink/60">
                            {{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format($week->starts_on->month === $week->ends_on->month ? 'j' : 'M j') }}
                        </span>
                        @if ($current)
                            <span class="eyebrow rounded-full bg-grass px-2.5 py-1 text-[0.65rem] text-white">You are here</span>
                        @endif
                    </div>

                    <h3 class="mt-3 font-serif text-xl font-medium text-club">{{ $week->focus }}</h3>
                    <p class="mt-1 text-ink/75"><span class="font-semibold text-ink">Milestone:</span> {{ $week->milestone }}</p>

                    <ul class="mt-4 space-y-2 border-t border-line pt-4">
                        @foreach ($week->tasks as $task)
                            <li class="group flex items-start gap-3 text-ink/85">
                                <span class="mt-1 size-4 shrink-0 rounded-full border-2 border-grass/60" aria-hidden="true"></span>
                                @if ($editable)
                                    <details class="min-w-0 flex-1">
                                        <summary class="flex cursor-pointer list-none items-start justify-between gap-3 [&::-webkit-details-marker]:hidden">
                                            <span>{{ $task->description }}</span>
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
                                    <span>{{ $task->description }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($editable)
                        <form method="POST" action="{{ route('roadmap.tasks.store', $week) }}" class="mt-3 flex gap-2">
                            @csrf
                            <label for="new-task-{{ $week->id }}" class="sr-only">Add a task to {{ $week->label($weeksPerStretch) }}</label>
                            <input id="new-task-{{ $week->id }}" name="description" maxlength="250" required placeholder="Add your own task"
                                class="min-w-0 flex-1 rounded-lg border border-dashed border-line bg-ivory/60 px-3 py-1.5 text-sm outline-none placeholder:text-ink/40 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10">
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-club hover:bg-mint">Add</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@else
    @if ($status === App\RoadmapStatus::Pending)
        @push('head')
            <meta http-equiv="refresh" content="6">
        @endpush
    @endif

    <section class="mt-6 overflow-hidden rounded-2xl border border-line bg-white">
        <div class="relative h-28 overflow-hidden bg-club">
            <x-field-backdrop class="absolute inset-0 size-full" />
        </div>
        <x-goal-line />
        <div class="px-8 py-10 text-center">
            <p class="eyebrow text-club">Your roadmap</p>

            @if ($status === App\RoadmapStatus::Pending)
                <h2 class="mt-3 font-serif text-2xl font-medium text-club">Building your game plan&hellip;</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">
                    GoalLine is mapping out your plan from today to {{ $goal->target_date->format('F j') }}. This usually takes under a minute, and this page will update on its own.
                </p>
                <div class="mx-auto mt-6 h-1.5 w-40 overflow-hidden rounded-full bg-mint" aria-hidden="true">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-grass"></div>
                </div>
            @elseif ($status === App\RoadmapStatus::Failed)
                <h2 class="mt-3 font-serif text-2xl font-medium text-club">We couldn't build your roadmap just now</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">Nothing's lost. Give it another try.</p>
            @else
                <h2 class="mt-3 font-serif text-2xl font-medium text-club">Get your game plan</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">
                    Turn this goal into a dated, GoalWeek-by-GoalWeek roadmap built for {{ Str::lower($goal->category->label()) }} goals.
                </p>
            @endif

            @if ($status !== App\RoadmapStatus::Pending && $canEdit)
                <form method="POST" action="{{ route('goals.roadmap.store', $goal) }}" class="mt-6">
                    @csrf
                    <button type="submit" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory transition hover:bg-club-dark">
                        {{ $status === App\RoadmapStatus::Failed ? 'Try again' : 'Build my roadmap' }}
                    </button>
                </form>
            @endif
        </div>
    </section>
@endif

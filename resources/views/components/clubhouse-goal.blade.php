{{-- A goal's current GoalWeek on the Clubhouse: live result, progress, and quick check-offs. --}}
@props(['goal'])

@php
    $week = $goal->currentWeek();
    $result = $week?->result();
    $notStarted = $week?->isNotStarted() ?? false;
    $closeCall = $week?->isCloseCall() ?? false;
    $planned = $week?->tasks->reject->is_bonus ?? collect();
    $done = $planned->filter->completed_at->count();
@endphp

<section id="goal-{{ $goal->id }}" class="scroll-mt-24 overflow-hidden rounded-2xl border border-line bg-white">
    <header class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
        <div class="min-w-0">
            <p class="eyebrow text-[0.62rem] text-ink/50">
                {{ $goal->category->label() }} &middot; <x-days-left :days="$goal->daysRemaining()" />
            </p>
            <a href="{{ route('goals.show', $goal) }}" class="mt-1 block font-serif text-xl font-medium text-club hover:underline">{{ $goal->title }}</a>
        </div>
        @if ($result)
            <x-goalweek-result :result="$result" :close-call="$closeCall" :not-started="$notStarted" />
        @endif
    </header>

    @if ($week)
        <div class="px-5 pt-3 pb-5">
            <p class="text-sm text-ink/70">
                <span class="font-semibold text-ink">{{ $week->label() }}:</span> {{ $week->focus }}
            </p>
            <x-goal-progress class="mt-3" :percent="$week->completionShare() * 100" label="Planned tasks done this GoalWeek" />
            <p class="mt-1 text-xs text-ink/55">{{ $done }} of {{ $planned->count() }} planned {{ Str::plural('task', $planned->count()) }} done{{ $week->milestone_hit_at ? ' · milestone hit' : '' }}</p>

            <ul class="mt-4 space-y-1.5">
                @foreach ($week->tasks as $task)
                    <li class="flex items-start gap-3">
                        <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}">
                            @csrf
                            <input type="hidden" name="return" value="clubhouse">
                            <button type="submit" title="{{ $task->completed_at ? 'Mark as not done' : 'Check off' }}" @class([
                                'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition',
                                'border-grass bg-grass text-white' => $task->completed_at,
                                'border-grass/60 hover:border-grass hover:bg-mint' => ! $task->completed_at,
                            ])>
                                @if ($task->completed_at)
                                    <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                                @endif
                                <span class="sr-only">{{ $task->completed_at ? 'Done: ' : 'Check off: ' }}{{ $task->description }}</span>
                            </button>
                        </form>
                        <span @class(['text-sm', 'text-ink/50 line-through decoration-ink/30' => $task->completed_at, 'text-ink/85' => ! $task->completed_at])>
                            {{ $task->description }}
                            @if ($task->is_bonus)
                                <span class="eyebrow ml-1 rounded-full bg-gold/25 px-1.5 py-0.5 text-[0.55rem] text-ink no-underline">Bonus</span>
                            @endif
                        </span>
                    </li>
                @endforeach
                <li class="flex items-start gap-3 border-t border-line pt-2">
                    <form method="POST" action="{{ route('goalweeks.milestone', $week) }}">
                        @csrf
                        <input type="hidden" name="return" value="clubhouse">
                        <button type="submit" title="{{ $week->milestone_hit_at ? 'Mark milestone as not hit' : 'Mark milestone as hit' }}" @class([
                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition',
                            'border-grass bg-grass text-white' => $week->milestone_hit_at,
                            'border-club/40 hover:border-club' => ! $week->milestone_hit_at,
                        ])>
                            @if ($week->milestone_hit_at)
                                <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                            @endif
                            <span class="sr-only">Milestone</span>
                        </button>
                    </form>
                    <span class="text-sm text-ink/85"><span class="font-semibold text-ink">Milestone:</span> {{ $week->milestone }}</span>
                </li>
            </ul>
        </div>
    @else
        <p class="px-5 pt-2 pb-5 text-sm text-ink/65">
            @switch($goal->roadmap_status)
                @case(App\RoadmapStatus::Pending)
                    Your roadmap is being built. It'll show up here in a minute.
                    @break
                @case(App\RoadmapStatus::Ready)
                    No GoalWeek this week. <a href="{{ route('goals.show', $goal) }}" class="font-semibold text-club hover:underline">Open the roadmap</a>
                    @break
                @default
                    No roadmap yet. <a href="{{ route('goals.show', $goal) }}" class="font-semibold text-club hover:underline">Build your game plan</a>
            @endswitch
        </p>
    @endif
</section>

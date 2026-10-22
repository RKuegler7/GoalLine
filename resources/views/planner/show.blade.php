@php
    $monday = $today->copy()->startOfWeek(Carbon\CarbonInterface::MONDAY);
    $days = collect(range(0, 6))->map(fn ($offset) => $monday->copy()->addDays($offset));
    $weeks = $goals->mapWithKeys(fn ($goal) => [$goal->id => $goal->currentWeek()]);
    $unplanned = $weeks->sum(fn ($week) => $week->tasks->whereNull('planned_on')->whereNull('completed_at')->count());
@endphp

<x-layouts.app title="Plan my week · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Clubhouse</a>
            <p class="eyebrow mt-6 text-mint">{{ $monday->format('M j') }}&ndash;{{ $monday->copy()->addDays(6)->format($monday->month === $monday->copy()->addDays(6)->month ? 'j' : 'M j') }}</p>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Plan my week</h1>
                    <p class="mt-2 max-w-xl text-ivory/85">Put each task on a day. It shows up on Today&rsquo;s Gameplan that morning, and anything you miss carries over.</p>
                </div>
                @if ($goals->isNotEmpty())
                    <form method="POST" action="{{ route('planner.auto') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="rounded-full bg-ivory px-5 py-2 text-sm font-semibold text-club transition hover:bg-white">
                            {{ $unplanned > 0 ? 'Plan the rest for me' : 'Auto-plan' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot:hero>

    @if ($goals->isEmpty())
        <section class="rounded-2xl border border-line bg-white px-6 py-8 text-center">
            <h2 class="font-serif text-2xl font-medium text-club">Nothing to plan yet</h2>
            <p class="mx-auto mt-2 max-w-md text-ink/70">Once a goal has a roadmap, this GoalWeek&rsquo;s tasks and habits show up here.</p>
            <a href="{{ route('dashboard') }}" class="mt-5 inline-block rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Back to the Clubhouse</a>
        </section>
    @else
        {{-- The week at a glance: what's on each day, across goals. --}}
        <section aria-label="Your week" class="-mx-4 overflow-x-auto px-4 pb-2">
            <ol class="grid min-w-[44rem] grid-cols-7 gap-2">
                @foreach ($days as $day)
                    @php
                        $tasks = $weeks->flatMap(fn ($week) => $week->tasksOn($day));
                        $habitCount = $goals->sum(fn ($goal) => $goal->habits->whereNull('archived_at')->filter(fn ($habit) => $habit->days ? in_array($day->dayOfWeekIso, $habit->days, true) : $habit->isDoneOn($day))->count());
                        $isToday = $day->isSameDay($today);
                    @endphp
                    <li @class([
                        'rounded-2xl border bg-white p-3',
                        'border-club ring-4 ring-mint' => $isToday,
                        'border-line' => ! $isToday,
                        'opacity-60' => $day->lt($today),
                    ])>
                        <p @class(['text-xs font-semibold uppercase tracking-wide', 'text-club' => $isToday, 'text-ink/50' => ! $isToday])>{{ $isToday ? 'Today' : $day->format('D') }}</p>
                        <p class="font-serif text-lg text-ink">{{ $day->format('j') }}</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($tasks as $task)
                                <li @class(['rounded-md px-1.5 py-1 text-xs leading-snug', 'bg-mint/70 text-ink/45 line-through' => $task->completed_at, 'bg-ivory text-ink/80' => ! $task->completed_at]) title="{{ $task->description }}">{{ Str::limit($task->description, 48) }}</li>
                            @endforeach
                        </ul>
                        @if ($habitCount > 0)
                            <p class="mt-2 flex items-center gap-1 text-xs text-club/70"><x-icon name="repeat" class="size-3" /> {{ $habitCount }} {{ Str::plural('habit', $habitCount) }}</p>
                        @endif
                        @if ($tasks->isEmpty() && $habitCount === 0)
                            <p class="mt-2 text-xs text-ink/35">Open</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Day pickers belong to the plan form below by id, so habit check-ins can keep their own forms. --}}
        <div class="mt-8 space-y-6">

            @foreach ($goals as $goal)
                @php
                    $week = $weeks[$goal->id];
                    $openDays = $planner->openDays($week);
                    $habits = $week->habits()->whereNull('archived_at');
                    $logFrom = $week->starts_on->max($monday)->copy();
                @endphp
                <section class="overflow-hidden rounded-2xl border border-line bg-white">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                        <div class="min-w-0">
                            <p class="eyebrow text-[0.62rem] text-ink/50">{{ $week->label() }} &middot; ends {{ $week->ends_on->format('D, M j') }}</p>
                            <a href="{{ route('goals.show', $goal) }}" class="mt-1 block font-serif text-xl font-medium text-club hover:underline">{{ $goal->title }}</a>
                            <p class="mt-0.5 text-sm text-ink/65">{{ $week->focus }}</p>
                        </div>
                        @if ($week->result())
                            <x-goalweek-result :result="$week->result()" :close-call="$week->isCloseCall()" :not-started="$week->isNotStarted()" />
                        @endif
                    </header>

                    <ul class="divide-y divide-line px-5">
                        @foreach ($week->tasks as $task)
                            <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <span @class(['min-w-0 text-sm', 'text-ink/45 line-through' => $task->completed_at, 'text-ink' => ! $task->completed_at])>
                                    {{ $task->description }}
                                    @if ($task->is_bonus)
                                        <span class="eyebrow ml-1 rounded-full bg-gold/25 px-1.5 py-0.5 text-[0.55rem] text-ink no-underline">Bonus</span>
                                    @endif
                                </span>
                                @if ($task->completed_at)
                                    <span class="shrink-0 text-xs font-semibold text-grass">Done {{ App\Clock::local($task->completed_at)->format('D') }}</span>
                                @else
                                    <label class="sr-only" for="plan-{{ $task->id }}">Day for {{ $task->description }}</label>
                                    <select id="plan-{{ $task->id }}" name="tasks[{{ $task->id }}]" form="week-plan" @class([
                                        'shrink-0 rounded-lg border bg-white px-2 py-1.5 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10 sm:w-44',
                                        'border-amber-400 text-amber-800' => $task->planned_on?->lt($today),
                                        'border-dashed border-club/40 text-ink/50' => ! $task->planned_on,
                                        'border-line' => $task->planned_on?->gte($today),
                                    ])>
                                        <option value="">No day yet</option>
                                        @if ($task->planned_on?->lt($today))
                                            <option value="{{ $task->planned_on->toDateString() }}" selected>{{ $task->planned_on->format('D, M j') }} (missed)</option>
                                        @endif
                                        @foreach ($openDays as $day)
                                            <option value="{{ $day->toDateString() }}" @selected($task->planned_on?->isSameDay($day))>{{ $day->isSameDay($today) ? 'Today' : $day->format('D, M j') }}</option>
                                        @endforeach
                                    </select>
                                    @error('tasks.'.$task->id)
                                        <p class="text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($habits->isNotEmpty())
                        <div class="border-t border-line bg-ivory/60 px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="eyebrow flex items-center gap-1.5 text-[0.65rem] text-club"><x-icon name="repeat" class="size-3.5" /> Habits</h3>
                                <a href="{{ route('goals.show', $goal) }}#habits" class="text-xs font-semibold text-club hover:underline">Edit habits</a>
                            </div>
                            <ul class="mt-2 space-y-2">
                                @foreach ($habits as $habit)
                                    <li class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm text-ink">{{ $habit->title }} <span class="text-ink/50">&middot; {{ $habit->cadence() }} &middot; {{ $habit->doneIn($week) }} of {{ $habit->targetFor($week) }} done</span></span>
                                        {{-- Forgot to log a day? Catch up on any day so far this week. --}}
                                        <span class="flex items-center gap-1.5">
                                            @for ($day = $logFrom->copy(); $day->lte($today); $day = $day->copy()->addDay())
                                                <span class="flex flex-col items-center">
                                                    <span class="text-[0.6rem] font-semibold text-ink/45" aria-hidden="true">{{ $day->format('D')[0] }}</span>
                                                    <x-habit-toggle :habit="$habit" :day="$day" return="planner" />
                                                </span>
                                            @endfor
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endforeach

        </div>

        <form id="week-plan" method="POST" action="{{ route('planner.update') }}" class="sticky bottom-20 mt-6 flex justify-end md:bottom-4">
            @csrf
            @method('PUT')
            <button type="submit" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory shadow-lg shadow-club/25 hover:bg-club-dark">Save my plan</button>
        </form>
    @endif
</x-layouts.app>

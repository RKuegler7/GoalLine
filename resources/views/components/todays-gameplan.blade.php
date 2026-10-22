{{-- Today's Gameplan: what's on for today across every goal, tasks and habits, with one-tap check-offs. --}}
@props(['gameplan'])

@php
    $total = $gameplan->total();
    $done = $gameplan->done();
    $unplanned = $gameplan->unplanned();
    $check = '<svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>';
@endphp

<section id="gameplan" {{ $attributes->class('scroll-mt-24 overflow-hidden rounded-2xl border border-club/25 bg-white') }}>
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-mint/50 px-5 py-4">
        <div>
            <h2 class="font-serif text-xl font-medium text-club">Today&rsquo;s Gameplan</h2>
            <p class="text-sm text-ink/60">
                @if ($total === 0)
                    Nothing on for today.
                @elseif ($done === $total)
                    All {{ $total }} done. Day won.
                @else
                    {{ $done }} of {{ $total }} done &middot; {{ $total - $done }} to go
                @endif
            </p>
        </div>
        <a href="{{ route('planner.show') }}" class="inline-flex items-center gap-1.5 rounded-full border border-club/30 bg-white px-4 py-1.5 text-sm font-semibold text-club transition hover:border-club">
            <x-icon name="calendar" class="size-4" /> Plan my week
        </a>
    </header>

    @if ($total > 0)
        <div class="px-5 pt-3">
            <x-goal-progress :percent="$done / $total * 100" label="Today's gameplan done" />
        </div>
    @endif

    <ul class="divide-y divide-line px-5 pb-2">
        @foreach ($gameplan->entries as $entry)
            @foreach ($entry['tasks'] as $task)
                <li class="flex items-start gap-3 py-3">
                    <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}">
                        @csrf
                        <input type="hidden" name="return" value="gameplan">
                        <button type="submit" title="{{ $task->completed_at ? 'Mark as not done' : 'Check off' }}" @class([
                            'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2 transition',
                            'border-grass bg-grass text-white' => $task->completed_at,
                            'border-grass/60 hover:border-grass hover:bg-mint' => ! $task->completed_at,
                        ])>
                            @if ($task->completed_at)
                                {!! $check !!}
                            @endif
                            <span class="sr-only">{{ $task->completed_at ? 'Done: ' : 'Check off: ' }}{{ $task->description }}</span>
                        </button>
                    </form>
                    <div class="min-w-0 flex-1">
                        <p @class(['text-ink/50 line-through decoration-ink/30' => $task->completed_at, 'text-ink' => ! $task->completed_at])>{{ $task->description }}</p>
                        <p class="mt-0.5 flex flex-wrap gap-x-2 text-xs text-ink/50">
                            <a href="{{ route('goals.show', $entry['goal']) }}" class="hover:text-club hover:underline">{{ $entry['goal']->title }}</a>
                            @if ($gameplan->isCarriedOver($task))
                                <span class="font-semibold text-amber-700">Carried over from {{ $task->planned_on->format('l') }}</span>
                            @endif
                            @if ($task->is_bonus)
                                <span class="font-semibold text-ink/70">Bonus</span>
                            @endif
                        </p>
                    </div>
                    @if ($gameplan->canMoveToTomorrow($task, $entry['week']))
                        <form method="POST" action="{{ route('roadmap.tasks.tomorrow', $task) }}">
                            @csrf
                            <button type="submit" class="rounded-full px-2.5 py-1 text-xs font-semibold text-ink/50 transition hover:bg-ivory hover:text-club">Tomorrow</button>
                        </form>
                    @endif
                </li>
            @endforeach

            @foreach ($entry['habits'] as $habit)
                @php
                    $doneToday = $habit->isDoneOn($gameplan->today);
                @endphp
                <li class="flex items-start gap-3 py-3">
                    <form method="POST" action="{{ route('habits.log', $habit) }}">
                        @csrf
                        <input type="hidden" name="return" value="gameplan">
                        <button type="submit" title="{{ $doneToday ? 'Undo' : 'Log it for today' }}" @class([
                            'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border-2 transition',
                            'border-grass bg-grass text-white' => $doneToday,
                            'border-club/40 hover:border-club hover:bg-mint' => ! $doneToday,
                        ])>
                            @if ($doneToday)
                                {!! $check !!}
                            @endif
                            <span class="sr-only">{{ $doneToday ? 'Done today: ' : 'Log for today: ' }}{{ $habit->title }}</span>
                        </button>
                    </form>
                    <div class="min-w-0 flex-1">
                        <p @class(['flex items-center gap-1.5', 'text-ink/50 line-through decoration-ink/30' => $doneToday, 'text-ink' => ! $doneToday])>
                            <x-icon name="repeat" class="size-3.5 text-club/60" />
                            {{ $habit->title }}
                        </p>
                        <p class="mt-0.5 text-xs text-ink/50">
                            {{ $entry['goal']->title }} &middot; {{ $habit->doneIn($entry['week']) }} of {{ $habit->targetFor($entry['week']) }} this GoalWeek
                        </p>
                    </div>
                </li>
            @endforeach
        @endforeach

        @foreach ($gameplan->challenges as $challenge)
            @php
                $doneToday = $gameplan->isChallengeDone($challenge);
            @endphp
            <li class="flex items-start gap-3 py-3">
                <form method="POST" action="{{ route('challenges.check-in', $challenge) }}">
                    @csrf
                    <input type="hidden" name="return" value="gameplan">
                    <button type="submit" title="{{ $doneToday ? 'Undo' : 'Check in for today' }}" @class([
                        'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border-2 transition',
                        'border-gold bg-gold text-ink' => $doneToday,
                        'border-gold/70 hover:border-gold hover:bg-gold/15' => ! $doneToday,
                    ])>
                        @if ($doneToday)
                            {!! $check !!}
                        @endif
                        <span class="sr-only">{{ $doneToday ? 'Done today: ' : 'Check in: ' }}{{ $challenge->title }}</span>
                    </button>
                </form>
                <div class="min-w-0 flex-1">
                    <p @class(['flex items-center gap-1.5', 'text-ink/50 line-through decoration-ink/30' => $doneToday, 'text-ink' => ! $doneToday])>
                        <x-icon name="flag" class="size-3.5 text-gold" />
                        {{ $challenge->title }}
                    </p>
                    <p class="mt-0.5 text-xs text-ink/50">
                        <a href="{{ route('challenges.show', $challenge) }}" class="hover:text-club hover:underline">Challenge &middot; day {{ $challenge->dayNumber() }} of {{ $challenge->lengthInDays() }}</a>
                    </p>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($total === 0 || $unplanned > 0)
        <p class="border-t border-line px-5 py-3 text-sm text-ink/65">
            @if ($unplanned > 0)
                {{ $unplanned }} {{ Str::plural('task', $unplanned) }} this GoalWeek {{ $unplanned === 1 ? "isn't" : "aren't" }} on a day yet.
            @else
                You're clear for today. Get ahead on tomorrow's work, or rest up.
            @endif
            <a href="{{ route('planner.show') }}" class="font-semibold text-club hover:underline">{{ $unplanned > 0 ? 'Plan them' : 'See the week' }}</a>
        </p>
    @endif
</section>

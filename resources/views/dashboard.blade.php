<x-layouts.app title="Clubhouse · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Your clubhouse</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">
                Welcome, {{ Str::before(auth()->user()->name, ' ') }}.
            </h1>
            <p class="mt-3 max-w-xl text-ivory/85">
                @if ($goals->isEmpty())
                    Let's set the goal you're working toward. Everything else builds from there.
                @else
                    Here's what you're working toward.
                @endif
            </p>
        </div>
    </x-slot:hero>

    {{-- Primary: the user's own goals. --}}
    @if ($goals->isEmpty())
        <section class="overflow-hidden rounded-2xl border border-line bg-white">
            <div class="relative h-36 overflow-hidden bg-club">
                <x-field-backdrop class="absolute inset-0 size-full" />
            </div>
            <x-goal-line />
            <div class="px-8 py-10 text-center">
                <p class="eyebrow text-club">Your goal</p>
                <h2 class="mt-3 font-serif text-2xl font-medium text-club">What do you want to accomplish?</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">
                    Name a goal and its date, and GoalLine will build your personal roadmap.
                </p>
                <a href="{{ route('goals.create') }}" class="mt-6 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory transition hover:bg-club-dark">
                    Set your first goal
                </a>
            </div>
        </section>
    @else
        <section>
            <div class="flex items-end justify-between gap-4">
                <h2 class="eyebrow text-club">Your goals</h2>
                <a href="{{ route('goals.create') }}" class="text-sm font-semibold text-club hover:underline">+ Add a goal</a>
            </div>

            <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($goals as $goal)
                    <li>
                        <a href="{{ route('goals.show', $goal) }}" class="group flex items-center justify-between gap-6 px-6 py-5 transition hover:bg-mint/50">
                            <div class="min-w-0">
                                <p class="eyebrow text-[0.68rem] text-ink/50">{{ $goal->category->label() }}</p>
                                <h3 class="mt-1 font-serif text-xl font-medium text-club">{{ $goal->title }}</h3>
                                <p class="mt-0.5 text-sm text-ink/60">By {{ $goal->target_date->format('F j, Y') }}</p>
                                <p class="mt-2 inline-block rounded-full bg-mint px-3 py-1 text-sm font-medium text-club sm:hidden">
                                    <x-days-left :days="$goal->daysRemaining()" />
                                </p>
                            </div>
                            <div class="hidden shrink-0 items-center gap-4 sm:flex">
                                <span class="rounded-full bg-mint px-3 py-1 text-sm font-medium text-club">
                                    <x-days-left :days="$goal->daysRemaining()" />
                                </span>
                                <span class="text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-club" aria-hidden="true">&rarr;</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Secondary: this week's focus, pace, and the optional league. --}}
    @php
        $thisWeek = $goals->map(fn ($goal) => $goal->currentWeek())->filter()->first();
    @endphp
    <dl class="mt-6 grid overflow-hidden rounded-2xl border border-line bg-white sm:grid-cols-3">
        @foreach ([
            $thisWeek
                ? ['This GoalWeek', $thisWeek->focus, $thisWeek->label().' of '.$thisWeek->goal->title]
                : ['This GoalWeek', 'No steps yet', 'Your current focus appears once a roadmap is ready.'],
            ($thisWeek && $thisWeek->isNotStarted())
                ? ['So far', 'Not started', 'New GoalWeek. Check off your first task to get going.']
                : (($thisWeek && $thisWeek->result())
                ? ['So far', $thisWeek->result()->label().($thisWeek->isCloseCall() ? ', close call' : ''), $thisWeek->result()->points().' '.Str::plural('point', $thisWeek->result()->points()).' this GoalWeek']
                : ['So far', '—', 'Check off tasks to see where you stand this GoalWeek.']),
            $leagues->isNotEmpty()
                ? ['League', $leagues->first()->name, $leagues->first()->status()->label().($leagues->count() > 1 ? ' · +'.($leagues->count() - 1).' more' : '')]
                : ['League', 'Optional', 'Join friends for encouragement and friendly accountability.'],
        ] as [$label, $value, $note])
            <div class="border-line px-6 py-5 max-sm:not-last:border-b sm:not-last:border-r">
                <dt class="eyebrow text-club">{{ $label }}</dt>
                <dd class="mt-2 font-serif text-2xl font-medium text-club">{{ $value }}</dd>
                <dd class="mt-1 text-sm text-ink/60">{{ $note }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($retired->isNotEmpty())
        <section class="mt-10">
            <h2 class="eyebrow text-ink/50">Retired goals</h2>
            <ul class="mt-3 space-y-1">
                @foreach ($retired as $goal)
                    <li>
                        <a href="{{ route('goals.show', $goal) }}" class="text-sm text-ink/60 hover:text-club hover:underline">{{ $goal->title }}</a>
                        <span class="text-sm text-ink/40">&middot; retired {{ $goal->retired_at?->format('M j, Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>

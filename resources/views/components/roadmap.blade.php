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

        @php
            $scored = $goal->weeks->filter(fn ($week) => $week->result() !== null);
        @endphp
        @if ($scored->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-2xl border border-line bg-white px-6 py-4">
                <div>
                    <p class="eyebrow text-club">GoalWeek points</p>
                    <p class="font-serif text-3xl font-medium text-club tabular-nums">{{ $goal->points() }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach (App\GoalWeekResult::cases() as $tier)
                        @php
                            $count = $scored->filter(fn ($week) => $week->result() === $tier)->count();
                        @endphp
                        @if ($count > 0)
                            <span class="text-sm text-ink/60">{{ $tier->label() }} <span class="font-semibold text-ink">&times;{{ $count }}</span></span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <ol class="mt-4 space-y-3">
            @foreach ($goal->weeks as $week)
                <x-goalweek-card :week="$week" :weeks-per-stretch="$weeksPerStretch" :can-edit="$canEdit" />
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

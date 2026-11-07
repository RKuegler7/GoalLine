@props(['goal'])

@php
    $status = $goal->roadmap_status;
    $weeksPerStretch = $goal->weeksPerStretch();
    $canEdit = $goal->isActive();
    // Until a plan is accepted (and nothing is checked off), it can be rebuilt whole; after that, only weeks that
    // haven't started can be re-planned, because a week's plan locks when it starts.
    $untouched = $goal->plan_accepted_at === null && ! $goal->weeks->contains(fn ($week) => App\Services\PlanEditor::hasCheckIns($week));
    $replanFrom = $untouched ? 1 : $goal->upcomingPosition();
    $aiPaused = $status === App\RoadmapStatus::Failed ? app(App\Services\AiBudget::class)->blockedReason($goal->user) : null;
    $editableWeeks = $canEdit && $status === App\RoadmapStatus::Ready ? app(App\Services\PlanEditor::class)->editableWeeks($goal) : collect();
@endphp

@if ($status === App\RoadmapStatus::Ready)
    <section class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <h2 class="eyebrow text-club">Your roadmap</h2>
            @if ($canEdit && $replanFrom)
                <form method="POST" action="{{ route('goals.roadmap.store', $goal) }}"
                    onsubmit="return confirm('{{ $replanFrom > 1 ? 'Re-plan the GoalWeeks still ahead? This week stays as it is.' : 'Build a fresh roadmap? This replaces the current one, including your task edits.' }}')">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-club hover:underline">
                        {{ $replanFrom > 1 ? 'Re-plan upcoming weeks' : ($goal->plan_source === 'fallback' ? 'Try the coach again' : 'Rebuild roadmap') }}
                    </button>
                </form>
            @endif
        </div>

        @if ($canEdit && $goal->plan_accepted_at === null)
            {{-- A new plan is a draft until the person has looked it over and accepted it. --}}
            <div class="mt-3 flex flex-col gap-3 rounded-2xl border-2 border-club bg-white px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-club">Draft plan: look it over</p>
                    <p class="text-sm text-ink/70">{{ $goal->plan_source === 'fallback' ? 'The coach was unavailable, so this is a starter plan built from your goal type and the minimum pace your deadline needs.' : 'Change anything you like below. Every week meets the minimum pace your deadline needs, checked by code, not the AI.' }}</p>
                </div>
                <form method="POST" action="{{ route('goals.plan.accept', $goal) }}" data-live>
                    @csrf
                    <button type="submit" class="w-full rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark sm:w-auto">Accept this plan</button>
                </form>
            </div>
        @endif

        <div class="mt-3 rounded-2xl border border-line bg-white px-6 py-5">
            <p class="font-serif text-xl leading-relaxed text-ink tracking-tight">{{ $goal->roadmap_summary }}</p>
            <p class="mt-3 border-t border-line pt-3 text-sm text-ink/60">
                @if ($weeksPerStretch > 1)
                    This is a longer goal, so each step of your plan spans <span class="font-semibold text-club">{{ $weeksPerStretch }} GoalWeeks</span>, with one milestone to hit before the next begins.
                @else
                    Your plan runs one <span class="font-semibold text-club">GoalWeek</span> at a time, with one milestone to hit each week.
                @endif
                @if ($canEdit)
                    Make it yours: refine it with the coach below, or edit, move, and reorder tasks yourself. Your coach checks you're still in line.
                @endif
            </p>
        </div>

        <x-coach-check :goal="$goal" />

        @if ($canEdit)
            <x-refine-plan :goal="$goal" :editable-weeks="$editableWeeks" />
        @endif

        @php
            $scored = $goal->weeks->filter(fn ($week) => $week->result() !== null);
        @endphp
        @if ($scored->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-2xl border border-line bg-white px-6 py-4">
                <div>
                    <p class="eyebrow text-club">GoalWeek points</p>
                    <p class="font-serif text-3xl font-bold text-club tabular-nums tracking-tight">{{ $goal->points() }}</p>
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
                <x-goalweek-card :week="$week" :weeks-per-stretch="$weeksPerStretch" :can-edit="$canEdit" :editable-weeks="$editableWeeks" />
            @endforeach
        </ol>

        <x-plan-history :goal="$goal" />
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
                <h2 class="mt-3 font-serif text-2xl font-bold text-club tracking-tight">Building your game plan&hellip;</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">
                    GoalLine is mapping out your plan from today to {{ $goal->target_date->format('F j') }}. This usually takes under a minute, and this page will update on its own.
                </p>
                <div class="mx-auto mt-6 h-1.5 w-40 overflow-hidden rounded-full bg-mint" aria-hidden="true">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-grass"></div>
                </div>
            @elseif ($status === App\RoadmapStatus::Failed)
                <h2 class="mt-3 font-serif text-2xl font-bold text-club tracking-tight">We couldn't build your roadmap just now</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">{{ $aiPaused ?? "Nothing's lost. Give it another try." }}</p>
            @else
                <h2 class="mt-3 font-serif text-2xl font-bold text-club tracking-tight">Get your game plan</h2>
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

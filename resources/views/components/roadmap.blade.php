@props(['goal'])

@php
    $status = $goal->roadmap_status;
    $stretchName = $goal->stretchName();
    $weeksPerStretch = $goal->weeksPerStretch();
@endphp

@if ($status === App\RoadmapStatus::Ready)
    <section class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <h2 class="eyebrow text-club">Your roadmap</h2>
            <form method="POST" action="{{ route('goals.roadmap.store', $goal) }}"
                onsubmit="return confirm('Build a fresh roadmap? This replaces the current one.')">
                @csrf
                <button type="submit" class="text-sm font-semibold text-club hover:underline">Rebuild roadmap</button>
            </form>
        </div>

        <div class="mt-3 rounded-2xl border border-line bg-white px-6 py-5">
            <p class="font-serif text-xl leading-relaxed text-ink">{{ $goal->roadmap_summary }}</p>
            <p class="mt-3 border-t border-line pt-3 text-sm text-ink/60">
                @if ($weeksPerStretch > 1)
                    This is a longer goal, so your plan runs in <span class="font-semibold text-club">{{ $weeksPerStretch }}-week match windows</span>. Each window has one milestone to hit before the next begins.
                @else
                    Your plan runs one <span class="font-semibold text-club">gameweek</span> at a time, with one milestone to hit each week.
                @endif
            </p>
        </div>

        <ol class="mt-4 space-y-3">
            @foreach ($goal->weeks as $week)
                @php
                    $current = $week->isCurrent();
                    $past = $week->isPast();
                @endphp
                <li @class([
                    'rounded-2xl border bg-white px-6 py-5',
                    'border-club ring-4 ring-mint' => $current,
                    'border-line' => ! $current,
                    'opacity-60' => $past,
                ])>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span @class([
                            'eyebrow rounded-full px-3 py-1 text-[0.68rem] tabular-nums',
                            'bg-club text-ivory' => $current,
                            'bg-mint text-club' => ! $current,
                        ])>{{ $stretchName }} {{ $week->position }}</span>
                        <span class="text-sm text-ink/60">
                            {{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format($week->starts_on->month === $week->ends_on->month ? 'j' : 'M j') }}
                        </span>
                        @if ($current)
                            <span class="eyebrow rounded-full bg-grass px-2.5 py-1 text-[0.65rem] text-white">This {{ Str::lower($stretchName) }}</span>
                        @endif
                    </div>

                    <h3 class="mt-3 font-serif text-xl font-medium text-club">{{ $week->focus }}</h3>
                    <p class="mt-1 text-ink/75"><span class="font-semibold text-ink">Milestone:</span> {{ $week->milestone }}</p>

                    <ul class="mt-4 space-y-2 border-t border-line pt-4">
                        @foreach ($week->tasks as $task)
                            <li class="flex items-start gap-3 text-ink/85">
                                <span class="mt-1 size-4 shrink-0 rounded-full border-2 border-grass/60" aria-hidden="true"></span>
                                {{ $task->description }}
                            </li>
                        @endforeach
                    </ul>
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
                    Turn this goal into a dated, step-by-step roadmap built for {{ Str::lower($goal->category->label()) }} goals.
                </p>
            @endif

            @if ($status !== App\RoadmapStatus::Pending)
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

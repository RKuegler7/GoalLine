@props(['goal'])

@php
    $checkStatus = $goal->plan_check_status;
    $verdict = $goal->plan_verdict;
    $needsAttention = $verdict && $verdict !== App\PlanVerdict::OnTrack;
    $dismissed = $needsAttention && $goal->plan_dismissed_at;
    $canAct = $goal->isActive();

    $fix = $goal->plan_fix;
    $fixWeek = $fix ? $goal->weeks->firstWhere('position', $fix['stretch']) : null;
    $fixPreview = $fixWeek ? match ($fix['action']) {
        'add_task' => "Add \u{201C}{$fix['new_text']}\u{201D} to ".$fixWeek->label(),
        'remove_task' => "Remove \u{201C}{$fix['task']}\u{201D} from ".$fixWeek->label(),
        'reword_task' => "Change \u{201C}{$fix['task']}\u{201D} to \u{201C}{$fix['new_text']}\u{201D}",
        default => null,
    } : null;
@endphp

@if ($checkStatus === App\RoadmapStatus::Pending)
    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-line bg-white px-6 py-4" role="status">
        <span class="mt-1.5 size-2.5 shrink-0 animate-pulse rounded-full bg-grass" aria-hidden="true"></span>
        <p class="text-ink/75">
            <span class="font-semibold text-club">Coach check in progress.</span>
            Your coach is reviewing your latest changes. Refresh in a moment to see whether you're still in line.
        </p>
    </div>
@elseif ($checkStatus === App\RoadmapStatus::Ready && $verdict && $dismissed)
    <details class="mt-4 rounded-2xl border border-line bg-white px-6 py-4">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm text-ink/70 [&::-webkit-details-marker]:hidden">
            <span>
                You kept your plan after an <span class="font-semibold">{{ Str::lower($verdict->label()) }}</span> coach check.
            </span>
            <span class="shrink-0 font-semibold text-club">Show coach's note</span>
        </summary>
        <div class="mt-3 border-t border-line pt-3">
            <p class="text-ink">{{ $goal->plan_note }}</p>
            @if ($goal->plan_suggestion)
                <p class="mt-2 text-ink/75"><span class="font-semibold text-ink">Coach's recommendation:</span> {{ $goal->plan_suggestion }}</p>
            @endif
            @if ($canAct && $fixPreview)
                <form method="POST" action="{{ route('goals.coach.fix', $goal) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="rounded-lg bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Apply fix: {{ $fixPreview }}</button>
                </form>
            @endif
        </div>
    </details>
@elseif ($checkStatus === App\RoadmapStatus::Ready && $verdict)
    <div @class([
        'mt-4 rounded-2xl border px-6 py-5',
        'border-grass/40 bg-mint' => $verdict === App\PlanVerdict::OnTrack,
        'border-amber-300 bg-amber-50' => $verdict === App\PlanVerdict::AtRisk,
        'border-red-300 bg-red-50' => $verdict === App\PlanVerdict::OffTrack,
    ])>
        <div class="flex flex-wrap items-center gap-3">
            <span class="eyebrow text-ink/60">Coach check</span>
            <span @class([
                'eyebrow rounded-full px-2.5 py-1 text-[0.65rem]',
                'bg-grass text-white' => $verdict === App\PlanVerdict::OnTrack,
                'bg-amber-500 text-white' => $verdict === App\PlanVerdict::AtRisk,
                'bg-red-600 text-white' => $verdict === App\PlanVerdict::OffTrack,
            ])>{{ $verdict->label() }}</span>
        </div>
        <p class="mt-2 text-ink">{{ $goal->plan_note }}</p>

        @if ($goal->plan_suggestion)
            <p class="mt-2 text-ink/75">
                <span class="font-semibold text-ink">{{ $needsAttention ? "Coach's recommendation:" : 'Keep going:' }}</span>
                {{ $goal->plan_suggestion }}
            </p>
        @endif

        @if ($needsAttention && $canAct)
            <div class="mt-4 flex flex-col gap-2 border-t border-ink/10 pt-4 sm:flex-row sm:items-center">
                @if ($fixPreview)
                    <form method="POST" action="{{ route('goals.coach.fix', $goal) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-club px-4 py-2.5 text-left text-sm font-semibold text-ivory hover:bg-club-dark sm:w-auto">
                            Apply fix <span class="font-normal text-ivory/80">&middot; {{ $fixPreview }}</span>
                        </button>
                    </form>
                @elseif ($fix === null)
                    <a href="#goalweek-{{ $goal->replanFromPosition() ?? 1 }}" class="rounded-lg bg-club px-4 py-2.5 text-center text-sm font-semibold text-ivory hover:bg-club-dark">
                        Adjust my tasks
                    </a>
                @endif
                <form method="POST" action="{{ route('goals.coach.dismiss', $goal) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-ink/70 hover:bg-white/60 hover:text-ink sm:w-auto">
                        Keep my plan
                    </button>
                </form>
            </div>
        @endif
    </div>
@elseif ($checkStatus === App\RoadmapStatus::Failed)
    <p class="mt-4 rounded-2xl border border-line bg-white px-6 py-4 text-sm text-ink/70">
        Your changes are saved, but your coach couldn't review them just now. The next edit will try again.
    </p>
@endif

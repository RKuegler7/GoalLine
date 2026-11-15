{{-- One Challenge in the Challenges tab, in gold: name, type and rule, your progress (completion, never stars), where
     it stands, and a one-tap check-in for the simple types (one Daily rule, Weekly, Abstain "on track"). A checklist or
     a number opens the Challenge. Expects the member's own check-ins loaded on $challenge->checkIns. --}}
@props(['challenge', 'me'])

@php
    $progress = $challenge->progressFor($me->id);
    $today = App\Models\Challenge::today();
    $simple = in_array($challenge->type, [App\Models\Challenge::WEEKLY, App\Models\Challenge::ABSTAIN], true)
        || ($challenge->type === App\Models\Challenge::DAILY && count($challenge->checklist()) === 1);
    $restDay = $challenge->sundays_off && $today->isSunday() && ! $challenge->isWeekly();
    $doneToday = $challenge->isCheckedIn($me, $today);
    $unit = $progress['unit'];
@endphp

<li {{ $attributes->class('flex items-center gap-2 rounded-xl border border-gold/60 bg-white py-2 pr-2 pl-3') }} data-challenge-card="{{ $challenge->id }}">
    <a href="{{ route('challenges.show', $challenge) }}" class="min-w-0 flex-1">
        <span class="flex items-center justify-between gap-2">
            <span class="truncate text-[15px] font-bold text-gold-ink">{{ $challenge->title }}</span>
            <span class="shrink-0 text-[13px] font-semibold text-ink tabular-nums">{{ $progress['completed'] }}/{{ $progress['total'] }}</span>
        </span>
        <span class="block truncate text-[13px] text-ink/75">{{ $challenge->typeLabel() }} &middot; {{ $challenge->ruleSummary() }}</span>
        <x-progress-bar :percent="$progress['percent']" tone="gold" class="mt-1.5" :label="'Your progress in '.$challenge->title" />
        <span class="mt-1 block truncate text-[13px] text-ink/75">
            @if (! $challenge->hasStarted())
                Starts {{ $challenge->starts_on->format('D, M j') }}
            @elseif ($challenge->isOver())
                {{ $progress['finished'] ? 'Complete' : 'Finished' }}
            @elseif ($challenge->isWeekly() && $progress['thisPeriod'])
                {{ $progress['thisPeriod']['count'] }} of {{ $progress['thisPeriod']['needed'] }} this week
            @else
                Day {{ $challenge->dayNumber() }} of {{ $challenge->lengthInDays() }} &middot; streak {{ $progress['streak'] }}
            @endif
            &middot; {{ $challenge->members_count ?? $challenge->members->count() }} {{ Str::plural('member', $challenge->members_count ?? $challenge->members->count()) }}
        </span>
    </a>
    @if ($challenge->isActive() && ! $restDay)
        @if ($simple)
            <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live class="shrink-0">
                @csrf
                <input type="hidden" name="return" value="list">
                <input type="hidden" name="do" value="{{ $doneToday ? 'undo' : 'done' }}">
                <button type="submit" @class([
                    'inline-flex min-h-11 items-center gap-1 rounded-lg px-2.5 text-[13px] font-semibold transition',
                    'bg-gold-light/50 text-ink' => $doneToday,
                    'border border-gold text-ink hover:bg-gold-light/40' => ! $doneToday,
                ]) title="{{ $doneToday ? 'Done today · tap to undo' : 'Check in for today' }}">
                    @if ($doneToday)
                        <x-icon name="check" class="size-4" /> Done today
                    @else
                        {{ $challenge->type === App\Models\Challenge::ABSTAIN ? 'On track today' : 'I did it today' }}
                    @endif
                </button>
            </form>
        @else
            <a href="{{ route('challenges.show', $challenge) }}" class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-gold px-2.5 text-[13px] font-semibold text-ink">{{ $doneToday ? 'Done today' : 'Check in' }}</a>
        @endif
    @endif
</li>

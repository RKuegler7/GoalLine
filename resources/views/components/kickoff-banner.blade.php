{{-- Before kickoff: when the season starts (with a live countdown) and what you can do now. $goal is a goal whose
     check-ins haven't opened yet; the date comes from Goal::checkInSeason(), the same rule the server enforces. --}}
@props(['goal'])

@php
    $season = $goal->checkInSeason();
    $kickoff = App\Clock::today()->setDateFrom($season['starts'])->startOfDay();
    $league = $season['league'];
@endphp

<section {{ $attributes->class('rounded-2xl border border-gold bg-gold/15 p-3') }} data-kickoff-banner aria-labelledby="kickoff-{{ $goal->id }}">
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <h2 id="kickoff-{{ $goal->id }}" class="text-[16px] font-bold text-ink">Your season kicks off {{ $kickoff->format('l, M j') }}</h2>
        <p class="text-[13px] font-semibold text-club tabular-nums" data-countdown="{{ $kickoff->toIso8601String() }}" aria-live="off">
            {{ max(0, (int) App\Clock::today()->diffInDays($kickoff)) }} {{ Str::plural('day', max(0, (int) App\Clock::today()->diffInDays($kickoff))) }} to go
        </p>
    </div>
    <p class="mt-1 text-[13px] text-ink/80">
        {{ $league ? $league->name.' · ' : '' }}{{ $goal->title }}. Get your plan just right before kickoff: check-ins open {{ $kickoff->format('D, M j') }} at 12:00 a.m. Eastern.
    </p>
    <div class="mt-2 flex flex-wrap gap-2 text-[13px]">
        <a href="{{ route('goals.show', $goal) }}#refine" class="inline-flex min-h-11 items-center rounded-full bg-club px-3.5 font-semibold text-ivory hover:bg-club-dark">Refine your plan</a>
        <a href="{{ route('goals.show', $goal) }}#goalweek-1" class="inline-flex min-h-11 items-center rounded-full border border-club/30 bg-white px-3.5 font-semibold text-club hover:border-club">Edit tasks</a>
        @if ($league)
            <a href="{{ route('leagues.show', $league) }}#invite" class="inline-flex min-h-11 items-center rounded-full border border-club/30 bg-white px-3.5 font-semibold text-club hover:border-club">Invite friends</a>
        @endif
        <button type="button" data-open-sheet="kickoff-sheet" class="inline-flex min-h-11 items-center px-1 font-semibold text-ink/70 hover:text-ink">Why can't I check in?</button>
    </div>
</section>

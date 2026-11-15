{{-- One member's Challenge, day by day (or week by week): what the Standings rows open, and the member's own page.
     Other members can dispute a recent check-in; the owner decides. Nothing private: no notes, no email. --}}
@php
    $unit = $row['unit'];
    $user = $member->user;
    $byDay = $checkIns->keyBy(fn ($checkIn) => $checkIn->checked_on->toDateString());
    $labels = ['done' => 'Done', 'missed' => 'Missed', 'slipped' => 'Slipped', 'rest' => 'Rest', 'open' => 'Still open', 'upcoming' => 'Coming up'];
    $windowStart = App\Models\Challenge::today()->subDays(App\Http\Controllers\ChallengeDisputeController::WINDOW_DAYS);
@endphp

<div class="space-y-3" data-challenge-member>
    <header class="flex items-center gap-3 pr-10">
        <x-avatar :user="$user" class="size-12 text-[15px]" />
        <div class="min-w-0">
            <h2 id="challenge-member-title" class="truncate text-[19px] leading-tight font-bold text-ink">{{ $user->name }}</h2>
            <p class="text-[13px] text-ink/75 tabular-nums">Place {{ $row['rank'] }} &middot; {{ $row['completed'] }} of {{ $row['total'] }} {{ Str::plural($unit, $row['total']) }} &middot; {{ $row['percent'] }}%</p>
        </div>
    </header>

    <dl class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded-lg bg-ivory px-1 py-2"><dt class="text-[11px] font-semibold text-ink/70">Streak</dt><dd class="text-[17px] font-bold tabular-nums">{{ $row['streak'] }}</dd></div>
        <div class="rounded-lg bg-ivory px-1 py-2"><dt class="text-[11px] font-semibold text-ink/70">Best</dt><dd class="text-[17px] font-bold tabular-nums">{{ $row['bestStreak'] }}</dd></div>
        <div class="rounded-lg bg-ivory px-1 py-2"><dt class="text-[11px] font-semibold text-ink/70">Verified</dt><dd class="text-[17px] font-bold tabular-nums">{{ $row['verified'] }}</dd></div>
    </dl>

    <section aria-labelledby="member-periods">
        <h3 id="member-periods" class="eyebrow mb-1.5 text-gold-ink">{{ Str::ucfirst($unit) }} by {{ $unit }}</h3>
        <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
            @foreach (array_reverse(array_filter($row['periods'], fn ($period) => $period['status'] !== 'upcoming')) as $period)
                @php $checkIn = $unit === 'day' ? $byDay->get($period['start']->toDateString()) : null; @endphp
                <li class="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2 text-[13px]">
                    <span class="w-24 shrink-0 font-semibold text-ink/80">{{ $unit === 'day' ? $period['start']->format('D, M j') : 'Week of '.$period['start']->format('M j') }}</span>
                    <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-gold text-ink' => $period['status'] === 'done', 'bg-red-100 text-red-800' => $period['status'] === 'slipped', 'bg-ivory text-ink/80 ring-1 ring-line' => ! in_array($period['status'], ['done', 'slipped'], true)])>
                        {{ $period['forgiven'] ? 'Miss forgiven' : $labels[$period['status']] }}
                    </span>
                    @if ($unit === 'week')
                        <span class="text-ink/75 tabular-nums">{{ $period['count'] }} of {{ $period['needed'] }}</span>
                    @endif
                    @if ($checkIn?->value !== null)
                        <span class="text-ink/75 tabular-nums">{{ rtrim(rtrim(number_format((float) $checkIn->value, 2), '0'), '.') }} {{ data_get($challenge->rules, 'unit') }}</span>
                    @endif
                    @if ($checkIn?->isVerified())
                        <span class="rounded-full bg-club px-2 py-0.5 text-[11px] font-semibold text-ivory">Verified</span>
                    @endif
                    @if ($checkIn?->status === App\Models\ChallengeCheckIn::VOIDED)
                        <span class="text-ink/70">Taken off after a dispute</span>
                    @endif
                    @if ($checkIn && $checkIn->status === App\Models\ChallengeCheckIn::DONE && $user->id !== $me->id && $checkIn->checked_on->gte($windowStart))
                        <details class="ml-auto">
                            <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-gold-ink">Dispute</summary>
                            <form method="POST" action="{{ route('challenges.disputes.store', [$challenge, $checkIn]) }}" class="mt-1 flex gap-2">
                                @csrf
                                <label class="sr-only" for="dispute-{{ $checkIn->id }}">Why (optional)</label>
                                <input id="dispute-{{ $checkIn->id }}" name="reason" maxlength="200" placeholder="Why? (optional)" class="min-w-0 flex-1 rounded-lg border border-line px-2 py-1.5 text-[13px]">
                                <button type="submit" class="min-h-11 rounded-full border border-line px-3 font-semibold text-ink">Send</button>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>

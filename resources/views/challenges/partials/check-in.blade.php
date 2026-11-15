{{-- One day's check-in for a Challenge, by type, updating in place (live forms):
     - Daily: each rule on the checklist ticks on its own (one tap with one rule);
     - Weekly: one tap for today, with "3 of 4 this week";
     - Abstain: "Stayed on track" or "Slipped today" (kind words either way);
     - Target: a number, with an "are you sure?" for unusual ones.
     Then optional proof: a photo added the same day earns the Verified check. --}}

@php
    $checkIn = $challenge->checkInFor($me, $day);
    $complete = $checkIn && $challenge->checkInCompletes($checkIn);
    $isToday = $day->isSameDay(App\Models\Challenge::today());
    $dayName = $isToday ? 'today' : 'yesterday';
    $rules = $challenge->rules ?? [];
    $confirm = session('challenge_confirm');
    $button = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full px-4 text-[15px] font-semibold transition';
@endphp

<div class="space-y-2" data-challenge-checkin="{{ $day->toDateString() }}">
    @if ($checkIn?->status === App\Models\ChallengeCheckIn::VOIDED)
        <p class="text-[13px] text-ink/75">This day's check-in was taken off after a dispute.</p>
    @elseif ($challenge->type === App\Models\Challenge::DAILY && count($challenge->checklist()) > 1)
        <ul class="space-y-1.5">
            @foreach ($challenge->checklist() as $index => $rule)
                @php $ticked = in_array($index, $checkIn?->rules_done ?? [], true); @endphp
                <li>
                    <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live>
                        @csrf
                        <input type="hidden" name="do" value="rule">
                        <input type="hidden" name="rule" value="{{ $index }}">
                        <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                        <button type="submit" aria-pressed="{{ $ticked ? 'true' : 'false' }}" @class(['flex min-h-11 w-full items-center gap-2.5 rounded-xl border px-3 text-left text-[15px]', 'border-gold bg-gold-light/40 font-semibold text-ink' => $ticked, 'border-line bg-white text-ink hover:border-gold' => ! $ticked])>
                            <span @class(['grid size-6 shrink-0 place-items-center rounded-full border-2', 'border-gold-ink bg-gold text-ink' => $ticked, 'border-ink/30' => ! $ticked]) aria-hidden="true">@if ($ticked)<x-icon name="check" class="size-4" />@endif</span>
                            {{ $rule }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
        <p class="text-[13px] text-ink/75">{{ count($checkIn?->rules_done ?? []) }} of {{ count($challenge->checklist()) }} done {{ $dayName }}. {{ $complete ? 'This day counts.' : 'The day counts when all are checked.' }}</p>
    @elseif ($challenge->type === App\Models\Challenge::ABSTAIN)
        @if ($checkIn)
            <p @class(['rounded-xl px-3 py-2.5 text-[15px] font-semibold', 'bg-gold-light/40 text-ink' => $complete, 'bg-ivory text-ink' => ! $complete])>
                {{ $complete ? 'On track '.$dayName.'. Nice.' : 'Slipped '.$dayName.'. That\'s one day, not the whole Challenge.' }}
            </p>
        @endif
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live>
                @csrf
                <input type="hidden" name="do" value="{{ $complete ? 'undo' : 'done' }}">
                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                <button type="submit" aria-pressed="{{ $complete ? 'true' : 'false' }}" @class([$button, 'bg-gold text-ink' => ! $complete, 'border border-gold text-ink' => $complete])>
                    <x-icon name="check" class="size-4" /> {{ $complete ? 'Undo' : 'Stayed on track' }}
                </button>
            </form>
            @unless ($checkIn && ! $complete)
                <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live>
                    @csrf
                    <input type="hidden" name="do" value="slipped">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <button type="submit" class="{{ $button }} border border-line text-ink/80 hover:border-ink/40">Slipped {{ $dayName }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live>
                    @csrf
                    <input type="hidden" name="do" value="undo">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <button type="submit" class="{{ $button }} border border-line text-ink/80">Undo</button>
                </form>
            @endunless
        </div>
    @elseif ($challenge->type === App\Models\Challenge::TARGET)
        <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live class="flex flex-wrap items-end gap-2">
            @csrf
            <input type="hidden" name="do" value="value">
            <input type="hidden" name="date" value="{{ $day->toDateString() }}">
            @if ($confirm && $confirm['date'] === $day->toDateString())
                <input type="hidden" name="confirmed" value="1">
            @endif
            <label class="min-w-0 flex-1">
                <span class="mb-1 block text-[13px] font-semibold text-ink/80">{{ $rules['label'] ?? 'Your number' }} {{ $dayName }} <span class="font-normal text-ink/70">(target {{ number_format((float) ($rules['threshold'] ?? 0)) }} {{ $rules['unit'] ?? '' }})</span></span>
                <input name="value" type="number" inputmode="decimal" min="0" step="any" max="{{ $rules['max'] ?? '' }}" value="{{ old('value', $confirm['value'] ?? ($checkIn?->value !== null ? (float) $checkIn->value : '')) }}"
                    class="block w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[16px] tabular-nums outline-none focus:border-gold-ink focus:ring-4 focus:ring-gold/20" aria-describedby="target-help-{{ $day->toDateString() }}">
            </label>
            <button type="submit" class="{{ $button }} bg-gold text-ink">{{ $confirm && $confirm['date'] === $day->toDateString() ? 'Yes, save it' : 'Save' }}</button>
        </form>
        <p id="target-help-{{ $day->toDateString() }}" class="text-[13px] text-ink/75">
            @if ($confirm && $confirm['date'] === $day->toDateString())
                That's well above the target. If it's right, save it again.
            @elseif ($checkIn?->value !== null)
                Logged {{ rtrim(rtrim(number_format((float) $checkIn->value, 2), '0'), '.') }} {{ $rules['unit'] ?? '' }}. {{ $challenge->isWeekly() ? 'It adds up toward the week.' : ($complete ? 'Target hit.' : 'Not at the target yet.') }}
            @else
                Enter the number when you have it. Save 0 to clear it.
            @endif
        </p>
        @error('value') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
    @else
        {{-- Daily with one rule, and Weekly: one tap. --}}
        <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live>
            @csrf
            <input type="hidden" name="do" value="done">
            <input type="hidden" name="date" value="{{ $day->toDateString() }}">
            <button type="submit" aria-pressed="{{ $complete ? 'true' : 'false' }}" @class([$button, 'w-full sm:w-auto', 'bg-gold text-ink' => ! $complete, 'border border-gold bg-gold-light/40 text-ink' => $complete])>
                <x-icon name="check" class="size-4" /> {{ $complete ? 'Done '.$dayName.' · tap to undo' : 'I did it '.$dayName }}
            </button>
        </form>
        @if ($challenge->isWeekly() && $progress['thisPeriod'])
            <p class="text-[13px] font-semibold text-ink tabular-nums" data-this-week>{{ $progress['thisPeriod']['count'] }} of {{ $progress['thisPeriod']['needed'] }} this week</p>
        @endif
    @endif

    {{-- Proof: optional, for a day that counts. --}}
    @if ($checkIn && $checkIn->status === App\Models\ChallengeCheckIn::DONE)
        @if ($checkIn->proof_path)
            <p class="flex items-center gap-1.5 text-[13px] text-ink/80">
                @if ($checkIn->isVerified())<span class="rounded-full bg-club px-2 py-0.5 text-[11px] font-semibold text-ivory">Verified</span>@endif
                <a href="{{ route('challenges.proof.show', [$challenge, $checkIn]) }}" class="font-semibold text-gold-ink underline-offset-2 hover:underline" target="_blank" rel="noopener">See your proof photo</a>
            </p>
        @else
            <form method="POST" action="{{ route('challenges.proof', $challenge) }}" enctype="multipart/form-data" data-live="false" class="flex flex-wrap items-center gap-2 text-[13px]">
                @csrf
                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                <label class="min-w-0 flex-1"><span class="sr-only">Proof photo for {{ $dayName }}</span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="block w-full text-[13px] text-ink/80 file:mr-2 file:min-h-11 file:rounded-full file:border-0 file:bg-ivory file:px-3 file:font-semibold file:text-ink">
                </label>
                <button type="submit" class="inline-flex min-h-11 items-center rounded-full border border-gold px-3 font-semibold text-ink">Add proof</button>
            </form>
            <p class="text-[13px] text-ink/70">Optional. A photo added {{ $isToday ? 'today' : 'the same day' }} gets the Verified check.</p>
        @endif
        @error('photo') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
    @endif
</div>

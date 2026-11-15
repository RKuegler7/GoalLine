{{-- A public Challenge anyone can join, in Challenge gold: type and rule, members and the cap, kickoff or the day, length
     and the owner's name. Join opens the rules to confirm first. --}}
@props(['challenge', 'member' => false, 'example' => false])

@php
    $url = $member ? route('challenges.show', $challenge) : route('challenges.join', $challenge->invite_code);
    $buttonClass = $member ? 'border border-gold text-ink' : 'bg-gold text-ink hover:brightness-105';
@endphp

<li {{ $attributes->class('flex flex-col justify-between rounded-2xl border border-gold/60 bg-white px-4 py-3') }} data-public-challenge>
    <div>
        <p class="eyebrow text-[11px] text-gold-ink">
            Challenge &middot; {{ $challenge->typeLabel() }}
            @if ($example) &middot; Example @endif
        </p>
        <p class="mt-0.5 text-[16px] font-bold text-ink [overflow-wrap:anywhere]">{{ $challenge->title }}</p>
        <p class="text-[13px] text-ink/80">{{ $challenge->ruleSummary() }}</p>
        <p class="text-[13px] text-ink/75">
            {{ $challenge->members_count }}{{ $challenge->max_members ? ' of '.$challenge->max_members : '' }} {{ Str::plural('member', $challenge->members_count) }}
            &middot; {{ $challenge->hasStarted() ? 'Day '.$challenge->dayNumber().' of '.$challenge->lengthInDays() : 'Starts '.$challenge->starts_on->format('M j').' · '.$challenge->lengthInDays().' days' }}
        </p>
        <p class="text-[13px] text-ink/75">Started by {{ $challenge->creator?->name }}</p>
    </div>
    <a href="{{ $url }}"
        class="mt-2 inline-flex min-h-11 items-center justify-center rounded-full px-4 text-sm font-bold whitespace-nowrap {{ $buttonClass }}" data-join-public-challenge>{{ $member ? "You're in · Open" : 'Join' }}</a>
</li>

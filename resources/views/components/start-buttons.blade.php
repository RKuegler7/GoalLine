{{-- The way to start something: "Start a GoalLeague" (green; opens the sheet with both choices) beside "Start a challenge" (gold), and
     "Join with a code". Full width on phones, side by side on wider screens. Without JavaScript, Start goes straight to
     the GoalLeague form. Use once per page. --}}
@props(['tone' => 'light'])

<div {{ $attributes->class('grid grid-cols-2 gap-2 sm:flex sm:items-center') }} data-start-buttons data-start-choices>
    <a href="{{ route('leagues.create') }}" data-open-sheet="start-sheet"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-grass px-5 text-[15px] font-bold text-ink shadow-sm transition hover:brightness-105 active:scale-[0.98]">
        <x-icon name="plus" class="size-5" /> Start a GoalLeague
    </a>
    <a href="{{ route('challenges.create') }}" data-start="challenge"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-gold px-5 text-[15px] font-bold text-ink shadow-sm transition hover:brightness-105 active:scale-[0.98]">
        <x-icon name="flag" class="size-5" /> Start a challenge
    </a>
    <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" @class([
        'col-span-2 inline-flex min-h-11 items-center justify-center rounded-full px-4 text-[14px] font-semibold transition',
        'bg-ivory/15 text-ivory ring-1 ring-ivory/40 hover:bg-ivory/25' => $tone === 'light',
        'border border-club/30 bg-white text-club hover:border-club' => $tone === 'dark',
    ])>Join with a code</a>
</div>

<x-start-sheet />

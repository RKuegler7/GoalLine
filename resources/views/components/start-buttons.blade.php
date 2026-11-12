{{-- The way to start something: "Start a GoalLeague" (green; opens the sheet with both choices) beside "Start a Challenge" (gold), and
     "Join with a code". Full width on phones, side by side on wider screens. Without JavaScript, Start goes straight to
     the GoalLeague form. Use once per page. --}}
@props(['tone' => 'light'])

<div {{ $attributes->class('grid grid-cols-2 gap-2 sm:flex sm:items-center') }} data-start-buttons data-start-choices>
    <a href="{{ route('leagues.create') }}" data-open-sheet="start-sheet"
        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full bg-grass px-3 text-[14px] font-bold whitespace-nowrap text-ink shadow-sm transition hover:brightness-105 active:scale-[0.98] sm:px-5 sm:text-[15px]" data-start="league">
        <x-icon name="plus" class="size-4 shrink-0 sm:size-5" /> <span class="sm:hidden">New GoalLeague</span><span class="hidden sm:inline">Start a GoalLeague</span>
    </a>
    <a href="{{ route('challenges.create') }}" data-start="challenge"
        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full bg-gold px-3 text-[14px] font-bold whitespace-nowrap text-ink shadow-sm transition hover:brightness-105 active:scale-[0.98] sm:px-5 sm:text-[15px]">
        <x-icon name="flag" class="size-4 shrink-0 sm:size-5" /> <span class="sm:hidden">New Challenge</span><span class="hidden sm:inline">Start a Challenge</span>
    </a>
    <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" @class([
        'col-span-2 inline-flex min-h-11 items-center justify-center rounded-full px-4 text-[14px] font-semibold transition',
        'bg-ivory/15 text-ivory ring-1 ring-ivory/40 hover:bg-ivory/25' => $tone === 'light',
        'border border-club/30 bg-white text-club hover:border-club' => $tone === 'dark',
    ])>Join with a code</a>
</div>

<x-start-sheet />

{{-- The last card in a list of GoalLeagues: start another GoalLeague (green) or a Challenge (gold), or join with a code. --}}
<div class="rounded-xl border-2 border-dashed border-club/25 bg-white px-3 py-2.5" data-start-choices>
    <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('leagues.create') }}" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full bg-grass px-3 text-[14px] font-bold whitespace-nowrap text-ink hover:brightness-105" data-start="league">
            <x-icon name="plus" class="size-4 shrink-0" /> New GoalLeague
        </a>
        <a href="{{ route('challenges.create') }}" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full bg-gold px-3 text-[14px] font-bold whitespace-nowrap text-ink hover:brightness-105" data-start="challenge">
            <x-icon name="flag" class="size-4 shrink-0" /> New Challenge
        </a>
    </div>
    <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="mt-1.5 flex min-h-11 items-center justify-center text-[13px] font-semibold text-club hover:underline">Join with a code</a>
</div>

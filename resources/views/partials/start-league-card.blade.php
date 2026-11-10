{{-- The last card in a list of GoalLeagues: start another one, or join with a code. --}}
<div class="flex items-center justify-between gap-3 rounded-xl border-2 border-dashed border-club/25 bg-white px-3 py-2.5">
    <a href="{{ route('leagues.create') }}" data-open-sheet="start-sheet" class="flex min-h-11 items-center gap-2.5 font-bold text-club hover:underline">
        <span class="grid size-9 place-items-center rounded-full bg-grass text-ink"><x-icon name="plus" class="size-5" /></span>
        Start a new GoalLeague
    </a>
    <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="gl-tap relative text-[13px] font-semibold text-club hover:underline">Join with a code</a>
</div>

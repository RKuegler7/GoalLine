{{-- The "What do you want to start?" sheet: a GoalLeague or a Challenge, each with one line about it. Include once per page. --}}
<dialog id="start-sheet" data-start-sheet aria-labelledby="start-sheet-title"
    class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-2xl bg-ivory p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-md sm:rounded-2xl">
    <div class="flex items-center justify-between gap-3 px-4 pt-3">
        <h2 id="start-sheet-title" class="text-[17px] font-bold text-club">What do you want to start?</h2>
        <form method="dialog">
            <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/70 hover:bg-white" aria-label="Close"><x-icon name="x" class="size-5" /></button>
        </form>
    </div>
    <div class="grid gap-3 px-4 pt-2 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
        <a href="{{ route('leagues.create') }}" class="flex items-center gap-3 rounded-2xl border-2 border-club/20 bg-white px-4 py-4 transition hover:border-club">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-mint text-club"><x-icon name="leagues" class="size-6" /></span>
            <span>
                <span class="block text-[16px] font-bold text-club">Start a GoalLeague</span>
                <span class="block text-[13px] text-ink/70">A season with friends: everyone plays their own goal in a weekly Matchup.</span>
            </span>
        </a>
        <a href="{{ route('challenges.create') }}" class="flex items-center gap-3 rounded-2xl border-2 border-gold/40 bg-white px-4 py-4 transition hover:border-gold">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-gold/20 text-ink"><x-icon name="flag" class="size-6" /></span>
            <span>
                <span class="block text-[16px] font-bold text-ink">Start a Challenge</span>
                <span class="block text-[13px] text-ink/70">A short sprint together, like &ldquo;Run 20 days this month.&rdquo; One check-in a day.</span>
            </span>
        </a>
    </div>
</dialog>

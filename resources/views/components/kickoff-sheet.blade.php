{{-- Why a check-in isn't open yet (before kickoff, or a later day's task), and what you can do meanwhile. --}}
<dialog id="kickoff-sheet" data-kickoff-sheet aria-labelledby="kickoff-sheet-title"
    class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-2xl bg-ivory p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-2xl">
    <div class="flex items-center justify-between gap-3 border-b border-line bg-white px-4 py-1.5">
        <h2 id="kickoff-sheet-title" class="text-[18px] font-bold text-club">Not open yet</h2>
        <form method="dialog">
            <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/70 hover:bg-ivory" aria-label="Close"><x-icon name="x" class="size-5" /></button>
        </form>
    </div>
    <div data-sheet-scroll class="space-y-3 px-4 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] text-[14px] text-ink/85">
        <p>Check-ins count from kickoff: <span class="font-semibold">Monday at 12:00 a.m. Eastern</span> for a GoalLeague's first week, the Monday of the week you joined if you joined late, or your plan's first day for a solo goal. Each task opens on its own day.</p>
        <p>That keeps every league fair: nobody gets a head start.</p>
        <p class="font-semibold text-club">Get your plan just right before kickoff.</p>
        <ul class="list-disc space-y-1 pl-5">
            <li>Refine your plan in your own words, or edit tasks by hand.</li>
            <li>Invite friends to your GoalLeague.</li>
            <li>Set your goal if you haven't yet.</li>
        </ul>
    </div>
</dialog>

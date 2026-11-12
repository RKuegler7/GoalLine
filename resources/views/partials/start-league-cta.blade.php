{{-- No GoalLeague yet: a big, friendly way in. GoalLeagues are optional, so it's an invitation, not a nag. --}}
<div class="overflow-hidden rounded-2xl border border-club/20 bg-white">
    <div class="relative bg-club px-5 py-6">
        <x-field-backdrop class="absolute inset-0 size-full opacity-70" />
        <p class="relative text-[20px] leading-tight font-bold text-ivory">Play your goal with friends.</p>
        <p class="relative mt-1 text-[14px] text-ivory/90">Everyone brings their own goal. Each week you face one friend, scored against your own plans.</p>
    </div>
    <div class="grid grid-cols-2 gap-2 px-4 py-4" data-start-choices>
        <a href="{{ route('leagues.create') }}" class="inline-flex min-h-12 items-center justify-center gap-1.5 rounded-full bg-grass px-3 text-[14px] font-bold whitespace-nowrap text-ink hover:brightness-105 sm:text-[15px]" data-start="league">
            <x-icon name="plus" class="size-4 shrink-0 sm:size-5" /> <span class="sm:hidden">New GoalLeague</span><span class="hidden sm:inline">Start a GoalLeague</span>
        </a>
        <a href="{{ route('challenges.create') }}" class="inline-flex min-h-12 items-center justify-center gap-1.5 rounded-full bg-gold px-3 text-[14px] font-bold whitespace-nowrap text-ink hover:brightness-105 sm:text-[15px]" data-start="challenge">
            <x-icon name="flag" class="size-4 shrink-0 sm:size-5" /> <span class="sm:hidden">New Challenge</span><span class="hidden sm:inline">Start a Challenge</span>
        </a>
        <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="col-span-2 inline-flex min-h-12 items-center justify-center rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">Join with a code</a>
    </div>
</div>

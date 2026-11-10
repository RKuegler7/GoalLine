{{-- No GoalLeague yet: a big, friendly way in. GoalLeagues are optional, so it's an invitation, not a nag. --}}
<div class="overflow-hidden rounded-2xl border border-club/20 bg-white">
    <div class="relative bg-club px-5 py-6">
        <x-field-backdrop class="absolute inset-0 size-full opacity-70" />
        <p class="relative text-[20px] leading-tight font-bold text-ivory">Play your goal with friends.</p>
        <p class="relative mt-1 text-[14px] text-ivory/90">Everyone brings their own goal. Each week you face one friend, scored against your own plans.</p>
    </div>
    <div class="grid gap-2 px-4 py-4 sm:grid-cols-2">
        <a href="{{ route('leagues.create') }}" data-open-sheet="start-sheet" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-grass px-5 text-[15px] font-bold text-ink hover:brightness-105">
            <x-icon name="plus" class="size-5" /> Start a GoalLeague
        </a>
        <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="inline-flex min-h-12 items-center justify-center rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">Join with a code</a>
    </div>
</div>

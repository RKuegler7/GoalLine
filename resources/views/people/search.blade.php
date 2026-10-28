<x-layouts.app title="Find people · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ $inviteLeague ? route('leagues.show', $inviteLeague).'#invite' : route('friends.index') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $inviteLeague ? $inviteLeague->name : 'Friends' }}</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $inviteLeague ? 'Invite to '.$inviteLeague->name : 'Find people' }}</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Search by name or username, or by someone's exact email. Doing it with friends makes it stick.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        <x-people-search-form :query="$query" :league="$inviteLeague" label="Search" />
        <x-people-results :results="$results" :query="$query" :league="$inviteLeague" />
    </div>
</x-layouts.app>

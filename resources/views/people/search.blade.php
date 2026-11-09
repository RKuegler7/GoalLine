<x-layouts.app title="Find people · GoalLine">
    <x-slot:hero>
        <x-page-header :title="$inviteLeague ? 'Invite to '.$inviteLeague->name : 'Find people'"
            :back="$inviteLeague ? route('leagues.show', $inviteLeague).'#invite' : route('friends.index')" :back-label="$inviteLeague ? $inviteLeague->name : 'Friends'"
            subtitle="Search by name or username, or an exact email." />
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        <x-people-search-form :query="$query" :league="$inviteLeague" label="Search" />
        <x-people-results :results="$results" :query="$query" :league="$inviteLeague" />
    </div>
</x-layouts.app>

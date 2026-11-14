{{-- One player's season in a league, as its own page (the standings table opens the same content in a sheet). --}}
<x-layouts.app :title="$member->displayName().' · Standings · '.$league->name">
    <x-slot:hero>
        <x-page-header :title="$member->displayName()" :eyebrow="$league->name.' · rank '.$row['rank']" :back="route('leagues.standings', $league)" back-label="Standings" />
    </x-slot:hero>

    <div data-first-content>
        @include('leagues.partials.player-season')
    </div>
</x-layouts.app>

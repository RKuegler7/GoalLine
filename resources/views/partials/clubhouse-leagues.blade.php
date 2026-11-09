{{-- The Clubhouse's GoalLeagues: compact cards with this week's matchup, or a one-line way to start. --}}
@if ($leagues->isEmpty())
    <x-empty-state message="Go head-to-head every GoalWeek with friends. GoalLeagues are optional." action="Start a GoalLeague" :href="route('leagues.create')" />
@else
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
        @foreach ($leagues as $snapshot)
            <x-league-card :snapshot="$snapshot" />
        @endforeach
    </div>
@endif

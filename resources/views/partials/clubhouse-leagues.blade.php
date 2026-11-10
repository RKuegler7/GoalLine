{{-- The Clubhouse's GoalLeagues: compact cards with this week's matchup, then a clear way to start another one. --}}
@if ($leagues->isEmpty())
    @include('partials.start-league-cta')
@else
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
        @foreach ($leagues as $snapshot)
            <x-league-card :snapshot="$snapshot" />
        @endforeach
        @include('partials.start-league-card')
    </div>
@endif
<x-start-sheet />

{{-- The top of Clubhouse > Now: a GoalLeague still waiting on your goal. Set it, finish its questions, or watch the plan
     arrive. Needs $leagueSetup from DashboardController::leagueSetup(). --}}
@foreach ($leagueSetup as $setup)
    @php($league = $setup['league'])
    <section class="overflow-hidden rounded-xl border-2 border-club bg-white" data-league-setup="{{ $setup['state'] }}" aria-labelledby="setup-{{ $league->id }}">
        <div class="gl-field px-3 py-2.5 text-ivory">
            <p class="eyebrow text-[11px] text-mint">{{ $league->name }}</p>
            <h2 id="setup-{{ $league->id }}" class="text-[17px] leading-tight font-bold">
                @switch($setup['state'])
                    @case('needs_goal') Set your goal for {{ $league->name }} @break
                    @case('setting_up') Finish setting up your goal for {{ $league->name }} @break
                    @default Your plan for {{ $league->name }} is on its way
                @endswitch
            </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2 px-3 py-2.5">
            @switch($setup['state'])
                @case('needs_goal')
                    <p class="w-full text-[13px] text-ink/75">Your Matchups are scored on your own plan, so pick the goal you'll play.{{ $league->hasGoal() ? ' The league goal is ready to use: '.$league->goalSummary().'.' : '' }}</p>
                    <a href="{{ route('leagues.my-goal', $league) }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-4 text-[14px] font-semibold text-ivory hover:bg-club-dark">Set my goal</a>
                    @if ($setup['spare'])
                        <form method="POST" action="{{ route('leagues.membership.update', $league) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="goal_id" value="{{ $setup['spare']->id }}">
                            <button type="submit" class="inline-flex min-h-11 items-center rounded-full border border-club/30 px-4 text-[14px] font-semibold text-club hover:border-club">Use my goal in this league: {{ Str::limit($setup['spare']->title, 40) }}</button>
                        </form>
                    @endif
                    @break
                @case('setting_up')
                    <p class="w-full text-[13px] text-ink/75">&ldquo;{{ $setup['goal']->title }}&rdquo; has a few questions left. Your answers shape your plan.</p>
                    <a href="{{ route('goals.intake', ['goal' => $setup['goal'], 'from' => 'joined']) }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-4 text-[14px] font-semibold text-ivory hover:bg-club-dark">Answer the questions</a>
                    @break
                @default
                    <p class="w-full text-[13px] text-ink/75">&ldquo;{{ $setup['goal']->title }}&rdquo; is being planned. Look it over and tap Accept when it's ready.</p>
                    <a href="{{ route('goals.show', $setup['goal']) }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-4 text-[14px] font-semibold text-ivory hover:bg-club-dark">Open my goal</a>
            @endswitch
        </div>
    </section>
@endforeach

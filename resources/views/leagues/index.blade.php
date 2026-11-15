<x-layouts.app title="GoalLeagues & Challenges · GoalLine">
    <x-slot:hero>
        {{-- Title, then the big way to start: beside it on desktop, full width under it on phones. --}}
        <div class="mx-auto flex max-w-5xl flex-col gap-2.5 px-4 pt-1 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-[20px] leading-tight font-bold tracking-tight text-ivory md:text-[24px]">GoalLeagues</h1>
            <x-start-buttons class="w-full sm:w-auto" />
        </div>
    </x-slot:hero>

    @php
        $tab = $errors->hasAny(['code', 'challenge_code']) ? 'invites' : $tab;
        $tabs = [
            ['label' => 'GoalLeagues', 'segment' => 'leagues', 'count' => $snapshots->count(), 'active' => $tab === 'leagues'],
            ['label' => 'Challenges', 'segment' => 'challenges', 'count' => $challenges->count(), 'active' => $tab === 'challenges'],
            ['label' => 'Invites', 'segment' => 'invites', 'count' => ($invites->count() + $challengeInvites->count()) ?: null, 'active' => $tab === 'invites'],
        ];
    @endphp

    <div data-segmented data-first-content>
        <x-segmented :options="$tabs" label="Show" class="mb-3" />

        {{-- Your GoalLeagues: right at the top. --}}
        <section data-segment-panel="leagues" @if ($tab !== 'leagues') hidden @endif aria-label="Your GoalLeagues">
            @include('partials.public-row', ['kind' => 'leagues', 'id' => 'find-public-leagues', 'title' => 'Find a public GoalLeague', 'items' => $publicLeagues, 'seeAll' => route('leagues.browse'), 'empty' => 'No public GoalLeagues to join right now. Start one and make it public, or check back soon.'])
            @if ($snapshots->isEmpty())
                @include('partials.start-league-cta')
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($snapshots as $snapshot)
                        <x-league-card :snapshot="$snapshot" />
                    @endforeach
                    @include('partials.start-league-card')
                </div>
            @endif
        </section>

        {{-- Challenges: completion, never stars. --}}
        <section data-segment-panel="challenges" @if ($tab !== 'challenges') hidden @endif aria-label="Challenges">
            @include('partials.public-row', ['kind' => 'challenges', 'id' => 'find-public-challenges', 'title' => 'Find a public Challenge', 'items' => $publicChallenges, 'seeAll' => route('challenges.browse'), 'empty' => 'No public Challenges to join right now. Start one and make it public.'])
            @if ($challenges->isEmpty())
                <div class="rounded-2xl border border-gold/60 bg-white px-4 py-5 text-center" data-challenges-empty>
                    <p class="text-[17px] font-bold text-ink">No Challenges yet.</p>
                    <p class="mt-1 text-[13px] text-ink/75">A Challenge is one simple rule for a set time, like 10,000 steps a day or a dry month. Tap one to start:</p>
                    <ul class="mt-3 flex flex-wrap justify-center gap-1.5">
                        @foreach (array_slice(App\ChallengeTemplates::all(), 0, 6, true) as $key => $template)
                            <li><a href="{{ route('challenges.create', ['template' => $key]) }}" class="inline-flex min-h-11 items-center rounded-full bg-white px-3.5 text-[13px] font-semibold text-ink ring-1 ring-gold ring-inset hover:bg-gold-light/40">{{ $template['name'] }}</a></li>
                        @endforeach
                        <li><a href="{{ route('challenges.create') }}" class="inline-flex min-h-11 items-center rounded-full bg-gold px-3.5 text-[13px] font-semibold text-ink">Build your own</a></li>
                    </ul>
                </div>
            @else
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($challenges as $challenge)
                        <x-challenge-card :challenge="$challenge" :me="auth()->user()" />
                    @endforeach
                </ul>
            @endif
        </section>
        {{-- Invites, and joining with a code. --}}
        <section data-segment-panel="invites" @if ($tab !== 'invites') hidden @endif aria-label="Invites">
            @foreach ($challengeInvites as $challengeInvite)
                @php $challengeJoinUrl = route('challenges.join', $challengeInvite->challenge->invite_code); @endphp
                <div class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-gold/60 bg-white px-3 py-2.5" data-challenge-invite>
                    <a href="{{ $challengeJoinUrl }}" class="min-w-0 flex-1">
                        <span class="block [overflow-wrap:anywhere] text-[15px] font-bold text-gold-ink">{{ $challengeInvite->challenge->title }}</span>
                        <span class="block [overflow-wrap:anywhere] text-[13px] text-ink/75">Challenge &middot; from {{ $challengeInvite->inviter?->name ?? $challengeInvite->challenge->creator->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('challenges.invites.decline', $challengeInvite) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="min-h-11 px-2 text-[13px] font-semibold text-ink/70 hover:text-ink">Not for me</button>
                    </form>
                    <a href="{{ $challengeJoinUrl }}" class="shrink-0 text-[13px] font-semibold text-gold-ink">Open <span aria-hidden="true">&rarr;</span></a>
                </div>
            @endforeach
            @forelse ($invites as $invite)
                <a href="{{ $invite->url() }}" class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-club/30 bg-white px-3 py-2.5 hover:bg-mint/40">
                    <span class="min-w-0">
                        <span class="block [overflow-wrap:anywhere] text-[15px] font-bold text-club">{{ $invite->league->name }}</span>
                        <span class="block [overflow-wrap:anywhere] text-xs text-ink/70">From {{ $invite->league->commissioner->name }}</span>
                    </span>
                    <span class="shrink-0 text-[13px] font-semibold text-club">Open <span aria-hidden="true">&rarr;</span></span>
                </a>
            @empty
                @if ($challengeInvites->isEmpty())
                <x-empty-state class="mb-2" message="No invites waiting. Have a code from a friend? Join with it below." action="Join with a code" href="#join" />
                @endif
            @endforelse

            <div id="join" class="grid scroll-mt-20 gap-2 sm:grid-cols-2">
                <form method="POST" action="{{ route('leagues.find') }}" class="rounded-xl border border-line bg-white px-3 py-2.5">
                    @csrf
                    <label for="code" class="text-xs font-semibold text-ink/80">GoalLeague invite code</label>
                    <div class="mt-1 flex gap-2">
                        <input id="code" name="code" value="{{ old('code') }}" required placeholder="K7QX-M4PA" autocomplete="off" autocapitalize="characters"
                            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 font-mono text-sm tracking-widest uppercase outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        <button type="submit" class="min-h-10 rounded-lg bg-club px-4 text-sm font-semibold text-ivory hover:bg-club-dark">Find</button>
                    </div>
                    @error('code')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </form>
                <form method="POST" action="{{ route('challenges.find') }}" class="rounded-xl border border-line bg-white px-3 py-2.5">
                    @csrf
                    <label for="challenge_code" class="text-xs font-semibold text-ink/80">Challenge code</label>
                    <div class="mt-1 flex gap-2">
                        <input id="challenge_code" name="code" required placeholder="Have a Challenge code?" autocomplete="off" autocapitalize="characters"
                            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 font-mono text-sm tracking-widest uppercase outline-none placeholder:font-sans placeholder:tracking-normal placeholder:normal-case focus:border-club focus:ring-4 focus:ring-club/10">
                        <button type="submit" class="min-h-10 rounded-lg border border-club/30 px-4 text-sm font-semibold text-club hover:border-club">Join</button>
                    </div>
                    @error('challenge_code')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </section>
    </div>
</x-layouts.app>

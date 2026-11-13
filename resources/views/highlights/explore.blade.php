{{-- Explore: GoalLeagues whose owners listed them publicly, and posts their authors chose to make public. Nothing else
     ever appears here. In the demo, everything here is a made-up example. --}}
@php $demo = auth()->user()->isDemo(); @endphp

<x-layouts.app title="Explore · GoalLine">
    <x-slot:hero>
        <x-page-header title="Explore" eyebrow="Highlights" subtitle="Public GoalLeagues to join, and posts people chose to share with everyone." />
    </x-slot:hero>

    @include('highlights.partials.switch', ['active' => 'explore'])

    <div class="mx-auto max-w-2xl space-y-6" data-first-content data-explore>
        @if ($demo)
            <p class="rounded-xl bg-mint px-3 py-2 text-[13px] text-club" data-explore-examples>Everything in Explore here is a made-up example for the demo. Joining one only changes your demo.</p>
        @endif

        {{-- Inspiration: example goals that open "Start a GoalLeague" filled in. --}}
        @if ($inspiration !== [])
            <section aria-labelledby="explore-inspiration">
                <h2 id="explore-inspiration" class="eyebrow text-club">Inspiration</h2>
                <ul class="-mx-4 mt-2 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]" data-inspiration>
                    @foreach ($inspiration as $idea)
                        <li class="shrink-0">
                            <a href="{{ route('leagues.create', ['name' => $idea['name'], 'category' => $idea['category'], 'goal_title' => $idea['title']]) }}"
                                class="flex min-h-11 flex-col justify-center rounded-xl border border-line bg-white px-3 py-2 hover:border-club/40">
                                <span class="eyebrow text-[10px] text-ink/70">{{ App\GoalCategory::from($idea['category'])->label() }}</span>
                                <span class="text-[14px] font-semibold whitespace-nowrap text-club">{{ $idea['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Public GoalLeagues you can join. --}}
        @if (! request()->filled('before'))
            <section aria-labelledby="explore-leagues">
                <h2 id="explore-leagues" class="eyebrow text-club">Public GoalLeagues you can join</h2>
                @if ($leagues->isEmpty())
                    <p class="mt-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/70">No public GoalLeagues right now. Owners can list theirs from the league page.</p>
                @else
                    <ul class="mt-2 grid gap-2 sm:grid-cols-2" data-public-leagues>
                        @foreach ($leagues as $league)
                            <li class="flex flex-col justify-between rounded-2xl border border-line bg-white px-4 py-3" data-public-league>
                                <div>
                                    <p class="eyebrow text-[11px] text-ink/70">
                                        {{ App\Http\Controllers\ExploreController::categoryLabel($league->category) }}
                                        @if ($demo) &middot; Example @endif
                                    </p>
                                    <p class="mt-0.5 text-[16px] font-bold text-club [overflow-wrap:anywhere]">{{ $league->name }}</p>
                                    <p class="text-[13px] text-ink/75">{{ $league->members_count }} {{ Str::plural('member', $league->members_count) }} of {{ App\Models\League::MAX_MEMBERS }} &middot; {{ App\Http\Controllers\ExploreController::weekLabel($league) }}</p>
                                    @if ($league->description)
                                        <p class="mt-1 line-clamp-2 text-[13px] text-ink/80">{{ $league->description }}</p>
                                    @endif
                                </div>
                                @if (isset($memberOf[$league->id]))
                                    <a href="{{ route('leagues.show', $league) }}" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-full border border-club/30 px-4 text-sm font-semibold text-club">You're in · Open</a>
                                @else
                                    <form method="POST" action="{{ route('explore.join', $league) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full bg-grass px-4 text-sm font-bold whitespace-nowrap text-ink hover:brightness-105" data-join-public>Join</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- From the community: only posts their authors made Public (Explore). --}}
        <section aria-labelledby="explore-posts">
            <h2 id="explore-posts" class="eyebrow text-club">From the community</h2>
            @if ($posts->isEmpty())
                <p class="mt-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/70">No public posts yet. When you post, you can choose "Public (Explore)".</p>
            @else
                <div class="mt-2 space-y-5" data-public-posts>
                    @foreach ($posts as $post)
                        <div data-public-post>
                            @if ($demo)
                                <p class="eyebrow mb-1 text-[10px] text-ink/60">Example</p>
                            @endif
                            <x-highlight-card :highlight="$post" />
                        </div>
                    @endforeach
                </div>
                @if ($hasMore)
                    <div class="mt-6 text-center">
                        <a href="{{ route('highlights.explore', ['before' => $posts->last()->id]) }}" class="inline-flex min-h-11 items-center rounded-full border border-line bg-white px-5 text-sm font-semibold text-club hover:border-club/40">Load older posts</a>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-layouts.app>

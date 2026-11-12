{{-- The season finale: skippable cards, top to bottom. Replayable from History and the profile. Every number comes
     from the scoring code; the Goal Day answer is self-reported and never changes stars or standings. --}}
@php
    $result = $finale->result;
    $finish = $finale->finish();
    $stats = $finale->stats();
    $weeks = $finale->weeks();
    $medals = $finale->medals();
    $highlights = $finale->highlights();
    $news = $finale->leagueNews();
    $ideas = $finale->recommendations();
    $achieved = $finale->achieved();
    $ordinal = fn (int $n) => $n.(in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));

    $cards = array_values(array_filter([
        ['id' => 'finale-result', 'label' => 'Result'],
        $finish ? ['id' => 'finale-two', 'label' => 'Two results'] : null,
        $weeks->isNotEmpty() ? ['id' => 'finale-replay', 'label' => 'Replay'] : null,
        $highlights->isNotEmpty() ? ['id' => 'finale-reel', 'label' => 'Highlights'] : null,
        $news->isNotEmpty() ? ['id' => 'finale-news', 'label' => 'League news'] : null,
        ['id' => 'finale-medals', 'label' => 'Medals'],
        $preview ? null : ['id' => 'finale-share', 'label' => 'Share'],
        ['id' => 'finale-next', 'label' => 'Next goal'],
    ]));
    $nextOf = fn (string $id) => $cards[array_search($id, array_column($cards, 'id')) + 1]['id'] ?? null;
    $maxStars = max(1, (int) app(App\Services\ScoringRules::class)->weekRange()['max'], (int) $weeks->max('stars'));
    $p = $card;
    $png = fn (string $size) => route('finale.card', [$goal, $size, 'png']);
    $svg = fn (string $size) => route('finale.card', [$goal, $size, 'svg']);
@endphp

<x-layouts.app title="Season finale · GoalLine">
    <x-slot:hero>
        <x-page-header title="Season finale" :eyebrow="$goal->title" :back="route('dashboard', ['tab' => 'history'])" back-label="History">
            <a href="#finale-next" class="inline-flex min-h-9 items-center rounded-full bg-ivory/10 px-3 text-[13px] font-semibold text-ivory ring-1 ring-ivory/20 hover:bg-ivory/20" data-finale-skip>Skip</a>
        </x-page-header>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl space-y-4" data-finale @if ($preview) data-finale-preview @endif>
        @if ($preview)
            <p class="rounded-xl bg-mint px-3 py-2 text-[13px] text-club" data-finale-preview-note>Preview: this is how a season finale looks, with a finished demo season. Nothing here changes your demo.</p>
        @endif

        <nav aria-label="Finale cards" class="flex flex-wrap gap-1.5 text-[12px]">
            @foreach ($cards as $index => $item)
                <a href="#{{ $item['id'] }}" class="rounded-full bg-white px-2.5 py-1 font-semibold text-club ring-1 ring-line hover:ring-club/40">{{ $index + 1 }}. {{ $item['label'] }}</a>
            @endforeach
        </nav>

        {{-- 1. The result. --}}
        <section id="finale-result" data-finale-card="result" aria-labelledby="finale-result-title" @class([
            'relative scroll-mt-20 overflow-hidden rounded-2xl p-5 sm:p-6',
            'bg-gold text-ink' => $achieved,
            'bg-club text-ivory' => ! $achieved,
        ])>
            @if ($achieved)
                <div class="gl-finale-burst pointer-events-none absolute inset-0" aria-hidden="true"></div>
            @endif
            <div class="relative">
                <p @class(['eyebrow text-[11px]', 'text-ink/80' => $achieved, 'text-mint' => ! $achieved])>Goal Day{{ $result?->finished_early ? ' · finished early' : '' }}</p>
                @if ($achieved)
                    <h2 id="finale-result-title" class="mt-1 font-serif text-[32px] leading-tight font-bold">You did it.</h2>
                    <p class="mt-1 text-[15px]">{{ $goal->title }}</p>
                @elseif ($result)
                    <h2 id="finale-result-title" class="mt-1 font-serif text-[28px] leading-tight font-bold">You showed up for {{ $stats['weeks'] }} {{ Str::plural('week', $stats['weeks']) }}.</h2>
                    <p class="mt-1 text-[15px] text-ivory/90">{{ $result->outcome->label() }} on {{ $goal->title }}. Every week you played is in the book, and the next one starts from here.</p>
                @else
                    <h2 id="finale-result-title" class="mt-1 font-serif text-[28px] leading-tight font-bold">Season complete.</h2>
                    <p class="mt-1 text-[15px] text-ivory/90">{{ $stats['weeks'] }} {{ Str::plural('GoalWeek', $stats['weeks']) }} played on {{ $goal->title }}.</p>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2 text-[13px]">
                    @if ($result?->isVerified())
                        <span class="eyebrow rounded-full bg-club px-2 py-0.5 text-[11px] text-ivory" data-verified>Verified</span>
                    @endif
                    @if ($result?->actual_result)
                        <span @class(['rounded-full px-2.5 py-0.5 font-semibold', 'bg-white/70' => $achieved, 'bg-ivory/15' => ! $achieved])>Result: {{ $result->actual_result }}</span>
                    @endif
                    @if ($result && ! $result->shared)
                        <span @class(['text-[12px]', 'text-ink/75' => $achieved, 'text-ivory/80' => ! $achieved])>Private: only you see this.</span>
                    @endif
                </div>

                @if ($result?->helped_most || $result?->hardest_part)
                    <dl class="mt-3 space-y-1.5 text-[14px]">
                        @if ($result->helped_most)
                            <div><dt class="inline font-semibold">What helped most:</dt> <dd class="inline">{{ $result->helped_most }}</dd></div>
                        @endif
                        @if ($result->hardest_part)
                            <div><dt class="inline font-semibold">Hardest part:</dt> <dd class="inline">{{ $result->hardest_part }}</dd></div>
                        @endif
                    </dl>
                @endif

                @if ($result?->proof_path)
                    <img src="{{ $result->proofUrl() }}" alt="Goal Day proof" class="mt-3 max-h-48 rounded-xl object-cover" loading="lazy">
                @endif

                <div class="mt-4 flex flex-wrap gap-2 text-[14px]">
                    @if (! $preview && ! $result)
                        <a href="{{ route('goal-day.show', $goal) }}" class="inline-flex min-h-11 items-center rounded-full bg-ivory px-4 font-semibold text-club">Answer the Goal Day check-in</a>
                    @elseif (! $preview && $result->isEditable())
                        <a href="{{ route('goal-day.show', $goal) }}" @class(['inline-flex min-h-11 items-center rounded-full px-4 font-semibold ring-1', 'ring-ink/30 text-ink' => $achieved, 'ring-ivory/40 text-ivory' => ! $achieved])>Change my answer</a>
                    @endif
                    @if (! $achieved)
                        <a href="#finale-next" class="inline-flex min-h-11 items-center rounded-full bg-grass px-4 font-bold text-ink">Run it back</a>
                    @endif
                    @if ($next = $nextOf('finale-result'))
                        <a href="#{{ $next }}" @class(['ml-auto inline-flex min-h-11 items-center font-semibold', 'text-ink' => $achieved, 'text-ivory' => ! $achieved]) data-finale-next>Next &rarr;</a>
                    @endif
                </div>
            </div>
        </section>

        {{-- 2. Two results: the table and the goal are separate wins. --}}
        @if ($finish)
            <section id="finale-two" data-finale-card="two" aria-labelledby="finale-two-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5">
                <h2 id="finale-two-title" class="eyebrow text-club">Two results</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-ivory p-3 ring-1 ring-line ring-inset">
                        <p class="text-[12px] font-semibold text-ink/70">Table finish{{ $finish['final'] ? '' : ' (so far)' }}</p>
                        <p class="mt-0.5 text-[26px] leading-tight font-bold text-ink tabular-nums">{{ $ordinal($finish['rank']) }}</p>
                        <p class="text-[12px] text-ink/75">of {{ $finish['size'] }} &middot; {{ $finish['total'] }} {{ Str::plural('star', $finish['total']) }} &middot; {{ $finish['wins'] }}&ndash;{{ $finish['ties'] }}&ndash;{{ $finish['losses'] }}</p>
                    </div>
                    <div @class(['rounded-xl p-3 ring-1 ring-inset', 'bg-gold/30 ring-gold' => $achieved, 'bg-mint ring-line' => ! $achieved])>
                        <p class="text-[12px] font-semibold text-ink/70">Your goal</p>
                        <p class="mt-0.5 text-[20px] leading-tight font-bold text-ink">{{ $result ? $result->outcome->label() : 'Season complete' }}</p>
                        <p class="text-[12px] text-ink/75">{{ $result?->isVerified() ? 'Verified' : 'Self-reported' }}</p>
                    </div>
                </div>
                <p class="mt-3 text-[14px] text-ink/85">Finishing last while hitting your goal is still a win. The table measures your weeks; Goal Day is about you.</p>
                @if ($next = $nextOf('finale-two'))
                    <a href="#{{ $next }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-club" data-finale-next>Next &rarr;</a>
                @endif
            </section>
        @endif

        {{-- 3. The season replay: stars per GoalWeek (one series, so no legend), with a table for screen readers. --}}
        @if ($weeks->isNotEmpty())
            @php
                $bestStars = (int) $weeks->max('stars');
                $barW = 28;
                $gap = 6;
                $chartH = 120;
                $chartW = $weeks->count() * ($barW + $gap);
            @endphp
            <section id="finale-replay" data-finale-card="replay" aria-labelledby="finale-replay-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5">
                <h2 id="finale-replay-title" class="eyebrow text-club">Season replay</h2>
                <p class="mt-1 text-[13px] text-ink/75">Stars each GoalWeek (0 to {{ $maxStars }}). Tap or hover a bar for the week.</p>
                <div class="mt-3 overflow-x-auto">
                    <svg viewBox="0 0 {{ max($chartW, 200) }} {{ $chartH + 22 }}" width="{{ max($chartW, 200) }}" height="{{ $chartH + 22 }}" role="img" aria-label="Stars per GoalWeek" class="block max-w-none" data-replay-chart>
                        <line x1="0" y1="{{ $chartH }}" x2="{{ max($chartW, 200) }}" y2="{{ $chartH }}" stroke="currentColor" class="text-line" stroke-width="1" />
                        @foreach ($weeks as $index => $week)
                            @php
                                $h = $week['stars'] > 0 ? max(4, round($week['stars'] / $maxStars * ($chartH - 18))) : 0;
                                $x = $index * ($barW + $gap);
                                $isBest = $week['stars'] === $bestStars && $bestStars > 0;
                            @endphp
                            <g tabindex="0" class="outline-none focus-visible:opacity-80">
                                <title>{{ $week['label'] }}: {{ $week['stars'] }} {{ Str::plural('star', $week['stars']) }}, {{ $week['percent'] }}% of plan{{ $week['result'] ? ', '.$week['result']->label() : '' }}</title>
                                <rect x="{{ $x }}" y="0" width="{{ $barW }}" height="{{ $chartH }}" fill="transparent" />
                                @if ($h > 0)
                                    <path d="M{{ $x }},{{ $chartH }} v-{{ $h - 4 }} q0,-4 4,-4 h{{ $barW - 8 }} q4,0 4,4 v{{ $h - 4 }} z" fill="{{ $isBest ? '#d4ac58' : '#176b45' }}" />
                                @else
                                    <rect x="{{ $x }}" y="{{ $chartH - 2 }}" width="{{ $barW }}" height="2" fill="#176b45" opacity="0.3" />
                                @endif
                                @if ($isBest)
                                    <text x="{{ $x + $barW / 2 }}" y="{{ $chartH - $h - 4 }}" text-anchor="middle" font-size="11" font-weight="700" fill="#1e2922">{{ $week['stars'] }}</text>
                                @endif
                                <text x="{{ $x + $barW / 2 }}" y="{{ $chartH + 15 }}" text-anchor="middle" font-size="10" fill="#1e2922" opacity="0.7">{{ $week['number'] }}</text>
                            </g>
                        @endforeach
                    </svg>
                </div>
                <details class="mt-2 text-[13px]">
                    <summary class="cursor-pointer font-semibold text-club">Show as a table</summary>
                    <table class="mt-2 w-full text-left text-[13px] tabular-nums">
                        <thead><tr class="text-ink/70"><th class="py-1 font-semibold">Week</th><th class="font-semibold">Band</th><th class="font-semibold">% of plan</th><th class="font-semibold">Stars</th></tr></thead>
                        <tbody>
                            @foreach ($weeks as $week)
                                <tr class="border-t border-line"><td class="py-1">{{ $week['label'] }}</td><td>{{ $week['result']?->label() ?? '—' }}</td><td>{{ $week['percent'] }}%</td><td>{{ $week['stars'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>

                <dl class="mt-3 grid grid-cols-2 gap-1.5 sm:grid-cols-4" data-replay-stats>
                    @foreach (array_filter([
                        ['Total stars', $stats['totalStars'], null],
                        ['Best week', $stats['bestWeek'] ? $stats['bestWeek']['stars'].' ★' : '—', $stats['bestWeek'] ? $stats['bestWeek']['label'].' · '.$stats['bestWeek']['percent'].'%' : null],
                        ['Longest streak', $stats['longestStreak'], Str::plural('week', $stats['longestStreak']).' in line+'],
                        $finish ? ['Finish', $ordinal($finish['rank']), 'of '.$finish['size']] : null,
                        ['Tasks done', $stats['tasksDone'], null],
                        ['Proof posts', $stats['proofPosts'], null],
                        ['Cheers', $stats['cheers'], 'received'],
                        ['Weeks played', $stats['weeks'], null],
                    ]) as [$label, $value, $sub])
                        <div class="rounded-lg bg-ivory px-2.5 py-2 ring-1 ring-line ring-inset">
                            <dt class="truncate text-[11px] font-semibold text-ink/70">{{ $label }}</dt>
                            <dd class="text-[18px] leading-tight font-bold text-ink tabular-nums">{{ $value }}</dd>
                            @if ($sub)
                                <dd class="truncate text-[11px] text-ink/70">{{ $sub }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
                <p class="mt-2 text-[12px] text-ink/75">
                    @foreach ($stats['bands'] as $band => $count)
                        {{ $band }}: <span class="font-semibold tabular-nums">{{ $count }}</span>@if (! $loop->last) &middot; @endif
                    @endforeach
                </p>
                @if ($next = $nextOf('finale-replay'))
                    <a href="#{{ $next }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-club" data-finale-next>Next &rarr;</a>
                @endif
            </section>
        @endif

        {{-- 4. Highlights reel: this goal's most-cheered posts. --}}
        @if ($highlights->isNotEmpty())
            <section id="finale-reel" data-finale-card="reel" aria-labelledby="finale-reel-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5">
                <h2 id="finale-reel-title" class="eyebrow text-club">Highlights reel</h2>
                <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($highlights as $highlight)
                        <li class="overflow-hidden rounded-xl bg-ivory ring-1 ring-line ring-inset">
                            <a href="{{ route('highlights.index') }}#highlight-{{ $highlight->id }}" class="block">
                                @if ($highlight->photoUrl())
                                    <img src="{{ $highlight->photoUrl() }}" alt="{{ $highlight->caption ?? $highlight->headline }}" class="aspect-square w-full object-cover" loading="lazy">
                                @endif
                                <span class="block px-2 py-1.5">
                                    <span class="block truncate text-[12px] font-semibold text-ink">{{ $highlight->caption ?? $highlight->detail ?? $highlight->kind->label() }}</span>
                                    <span class="block text-[11px] text-ink/70">{{ $highlight->cheers_count }} {{ Str::plural('cheer', $highlight->cheers_count) }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($next = $nextOf('finale-reel'))
                    <a href="#{{ $next }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-club" data-finale-next>Next &rarr;</a>
                @endif
            </section>
        @endif

        {{-- 5. League news: league mates' shared Goal Day results (members only; private results never show). --}}
        @if ($news->isNotEmpty())
            <section id="finale-news" data-finale-card="news" aria-labelledby="finale-news-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5">
                <h2 id="finale-news-title" class="eyebrow text-club">{{ $finale->league->name }} &middot; Goal Day news</h2>
                @include('finale.partials.news', ['news' => $news, 'interactive' => ! $preview])
                @if ($next = $nextOf('finale-news'))
                    <a href="#{{ $next }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-club" data-finale-next>Next &rarr;</a>
                @endif
            </section>
        @endif

        {{-- 6. Medals. --}}
        <section id="finale-medals" data-finale-card="medals" aria-labelledby="finale-medals-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5">
            <h2 id="finale-medals-title" class="eyebrow text-club">Medals</h2>
            @if ($medals->isEmpty())
                <p class="mt-2 text-[14px] text-ink/80">No medals this season, and that's fine. Every week you showed up still counts, and next season starts fresh. <a href="{{ route('help', ['q' => 'medals']) }}" class="font-semibold text-club hover:underline">How medals work</a></p>
            @else
                <x-medal-shelf :medals="$medals" class="mt-2" />
            @endif
            @if ($next = $nextOf('finale-medals'))
                <a href="#{{ $next }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-club" data-finale-next>Next &rarr;</a>
            @endif
        </section>

        {{-- 7. Share your season. Never automatic. --}}
        @unless ($preview)
            <section id="finale-share" data-finale-card="share" aria-labelledby="finale-share-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5"
                data-result-card data-card-name="goalline-season-{{ Str::slug(Str::limit($goal->title, 30, '')) }}.png"
                data-card-title="{{ App\Services\ResultCards\SeasonCardImage::describe($p) }}" data-card-png="{{ $canMakePng ? '1' : '' }}">
                <h2 id="finale-share-title" class="eyebrow text-club">Share your season</h2>
                @if (! $achieved && $result && ! $result->shared)
                    <p class="mt-1 text-[13px] text-ink/75">You kept your Goal Day answer private, so the card just says "Season complete".</p>
                @endif
                <div data-segmented class="mt-3">
                    <x-segmented label="Card size" class="mx-auto mb-3 max-w-xs" :options="[
                        ['label' => 'Feed', 'segment' => 'feed', 'active' => true],
                        ['label' => 'Story', 'segment' => 'story'],
                    ]" />
                    <div data-segment-panel="feed" data-card-size="feed" data-card-png-url="{{ $png('feed') }}" data-card-svg-url="{{ $svg('feed') }}"
                        class="mx-auto max-w-[16rem] overflow-hidden rounded-2xl shadow-lg [&>svg]:block [&>svg]:h-auto [&>svg]:w-full">{!! app(App\Services\ResultCards\SeasonCardImage::class)->render($p, 'feed')['body'] !!}</div>
                    <div data-segment-panel="story" data-card-size="story" data-card-png-url="{{ $png('story') }}" data-card-svg-url="{{ $svg('story') }}" hidden
                        class="mx-auto max-w-[12rem] overflow-hidden rounded-2xl shadow-lg [&>svg]:block [&>svg]:h-auto [&>svg]:w-full">{!! app(App\Services\ResultCards\SeasonCardImage::class)->render($p, 'story')['body'] !!}</div>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <button type="button" data-card-share hidden
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-club px-5 text-[15px] font-semibold text-ivory hover:bg-club-dark">
                        <x-icon name="share" class="size-5" /> Share
                    </button>
                    <a href="{{ $canMakePng ? $png('feed') : $svg('feed') }}" download data-card-download
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">Download image</a>
                </div>
                <p data-card-status role="status" aria-live="polite" class="mt-2 text-xs text-ink/70"></p>

                <div class="mt-4 border-t border-line pt-3">
                    <h3 class="text-[14px] font-semibold text-ink">Post to Highlights</h3>
                    @if ($posted)
                        <a href="{{ route('highlights.index') }}#highlight-{{ $posted->id }}" class="mt-1 inline-flex min-h-10 items-center font-semibold text-club hover:underline">You posted it. See your post &rarr;</a>
                    @else
                        <form method="POST" action="{{ route('finale.post', $goal) }}" class="mt-2 space-y-2">
                            @csrf
                            <label for="season-caption" class="sr-only">Caption (optional)</label>
                            <textarea id="season-caption" name="caption" rows="2" maxlength="{{ App\Models\Highlight::MAX_CAPTION }}" placeholder="Caption (optional)"
                                class="block w-full rounded-lg border border-line bg-ivory px-3 py-2 text-base outline-none focus:border-club"></textarea>
                            <input type="hidden" name="visibility" value="{{ App\PostVisibility::LeagueFriends->value }}">
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full bg-grass px-5 text-sm font-bold text-ink hover:brightness-105">Post to Highlights</button>
                        </form>
                    @endif
                </div>

                <div class="mt-4 border-t border-line pt-3" data-region="season-link">
                    <h3 class="text-[14px] font-semibold text-ink">Link anyone can open</h3>
                    @if (App\Services\ResultCards\SeasonCardImage::isPublic($result))
                        <x-share-link class="mt-2" :url="route('finale.public', $result->share_token)" id="season-public-url" label="Your season card's link"
                            :share-message="'My GoalLine season: '.route('finale.public', $result->share_token)" share-title="My GoalLine season" />
                        <form method="POST" action="{{ route('finale.revoke', $goal) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-semibold text-ink/70 hover:text-ink hover:underline">Turn the link off</button>
                        </form>
                    @elseif (! $result)
                        <p class="mt-1 text-sm text-ink/75">Answer the Goal Day check-in to make a link.</p>
                    @elseif (! auth()->user()->public_cards)
                        <p class="mt-1 text-sm text-ink/80">Public card links are off for your account. <a href="{{ route('profile.edit') }}#public-cards" class="font-semibold text-club hover:underline">Change it in settings</a>.</p>
                    @else
                        <p class="mt-1 text-sm text-ink/80">Only this card: your first name, goal and season numbers. Never notes or proof. Turn it off anytime.</p>
                        <form method="POST" action="{{ route('finale.publish', $goal) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full border border-club/30 px-5 text-sm font-semibold text-club hover:border-club">Make a link</button>
                        </form>
                    @endif
                </div>
            </section>
        @endunless

        {{-- 8. Next goal. --}}
        <section id="finale-next" data-finale-card="next" aria-labelledby="finale-next-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4 sm:p-5">
            <h2 id="finale-next-title" class="eyebrow text-club">What's next?</h2>
            <p class="mt-1 text-[14px] text-ink/80">{{ $achieved ? 'You earned a step up. Here are a few ideas.' : 'Run it back, pick something new, or take a breather.' }}</p>
            <ul class="mt-3 space-y-2" data-recommendations>
                @foreach ($ideas as $idea)
                    <li>
                        <a href="{{ route('goals.create', ['title' => $idea['title'], 'category' => $idea['category'], 'target_date' => $idea['target_date']]) }}"
                            class="block rounded-xl border border-line px-3 py-2.5 hover:border-club/40" data-recommendation="{{ $idea['kind'] }}">
                            <span class="eyebrow text-[10px] text-club">{{ ['harder' => 'Step it up', 'again' => 'Run it back', 'new' => 'Something new'][$idea['kind']] ?? 'Idea' }} &middot; {{ $idea['weeks'] }} weeks</span>
                            <span class="block text-[15px] font-semibold text-ink">{{ $idea['title'] }}</span>
                            <span class="block text-[13px] text-ink/75">{{ $idea['why'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-3 flex flex-wrap gap-2 text-[14px]">
                <a href="{{ route('goals.create') }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-4 font-semibold text-ivory hover:bg-club-dark">Pick something new</a>
                @if ($finale->league)
                    <a href="{{ route('leagues.create', ['rematch' => $finale->league->id]) }}" class="inline-flex min-h-11 items-center rounded-full border border-club/30 px-4 font-semibold text-club hover:border-club" data-rematch>Run it back with the same friends</a>
                @endif
                <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="inline-flex min-h-11 items-center rounded-full border border-club/30 px-4 font-semibold text-club hover:border-club">Join a GoalLeague</a>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center rounded-full px-4 font-semibold text-ink/75 hover:text-ink" data-take-a-break>Take a break</a>
            </div>
        </section>
    </div>
</x-layouts.app>

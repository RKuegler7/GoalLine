{{-- About GoalLine: public, signed in or out. Every scoring number comes from App\Services\ScoringRules, which reads
     the same code that scores every GoalWeek, so this page can't drift from the rules. --}}
@php
    $viewer = auth()->user();
    $bands = $rules->bands();
    $meter = $rules->meter();
    $outcomes = $rules->outcomes();
    $range = $rules->weekRange();
    $example = $rules->example();
    [$avery, $sam] = $example['players'];
    $whatIf = $example['whatIf'];
    $bandColor = fn (App\GoalWeekResult $result) => match ($result) {
        App\GoalWeekResult::Short => 'bg-red-500',
        App\GoalWeekResult::InLine => 'bg-grass',
        App\GoalWeekResult::Past => 'bg-gradient-to-r from-gold via-[#f0dca4] to-gold',
        App\GoalWeekResult::Smashed => 'bg-sky-700',
    };
    $sections = [
        'what' => 'What it is',
        'mission' => 'Our goal',
        'why' => 'Why it exists',
        'made' => 'How it was made',
        'impact' => 'Why use it',
        'how-to-play' => 'How to play',
        'scoring' => 'Scoring',
        'fair-play' => 'Fair play',
        'ai' => 'The AI coach',
        'privacy' => 'Privacy',
        'faq' => 'FAQ',
        'contact' => 'Contact',
    ];
    $stars = fn (int $count) => $count.' '.Str::plural('star', $count);
@endphp

@push('head')
    @include('partials.share-meta', [
        'title' => 'About GoalLine · Get in line with your goals',
        'description' => 'A league for real goals: an AI coach builds your plan, you check in daily, and you play a friendly weekly matchup with friends. How to play, scoring and fair play.',
        'url' => App\ShareLink::route('about'),
    ])
    <style>
        @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
    </style>
@endpush

<x-dynamic-component :component="$viewer ? 'layouts.app' : 'layouts.field'" title="About · GoalLine">
    @unless ($viewer)
        <x-slot:nav>
            <a href="{{ route('login') }}" class="text-sm font-medium text-ivory/90 hover:text-ivory">Log in</a>
        </x-slot:nav>
    @endunless

    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-8 pb-14">
            <p class="eyebrow text-mint">About GoalLine</p>
            <h1 class="mt-3 max-w-3xl font-serif text-4xl font-bold tracking-tight text-balance text-ivory sm:text-6xl">Get in line with your goals.</h1>
            <p class="mt-3 font-serif text-xl text-ivory/90 tracking-tight sm:text-2xl">We get more done together.</p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                @if (! $viewer && config('goalline.demo.enabled'))
                    <a href="{{ route('demo') }}" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-gold px-6 py-2.5 font-semibold text-ink shadow-lg shadow-black/10 transition hover:brightness-105">Try the demo <span aria-hidden="true">&rarr;</span></a>
                @elseif ($viewer)
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center rounded-full bg-gold px-6 py-2.5 font-semibold text-ink transition hover:brightness-105">Back to your Clubhouse</a>
                @endif
                <a href="#how-to-play" class="inline-flex min-h-11 items-center rounded-full border border-ivory/40 px-6 py-2.5 font-semibold text-ivory transition hover:border-ivory">How to play</a>
            </div>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-3xl px-4 py-10">
        {{-- Anchor menu. Wraps on phones, so nothing scrolls sideways. --}}
        <nav aria-label="On this page" class="rounded-2xl border border-line bg-white px-4 py-4">
            <p class="eyebrow text-club">On this page</p>
            <ul class="mt-2 flex flex-wrap gap-2">
                @foreach ($sections as $anchor => $label)
                    <li><a href="#{{ $anchor }}" class="inline-flex min-h-10 items-center rounded-full bg-ivory px-3 text-sm font-semibold text-club ring-1 ring-line transition hover:bg-mint">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="mt-6 space-y-14 leading-relaxed text-ink/80 [&_h2]:scroll-mt-6 [&_section]:scroll-mt-6">
            {{-- 1. What it is --}}
            <section id="what" aria-labelledby="what-title">
                <p class="eyebrow text-club">What GoalLine is</p>
                <h2 id="what-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">A league for real goals</h2>
                <p class="mt-3">GoalLine is a league for the goals you actually care about: the exam, the thesis, the race, the lift. An AI coach turns your goal into a dated plan of weekly GoalWeeks. You check in every day, and each week you play a friendly matchup with a friend in your GoalLeague. The scoreboard is fun, but what matters most is hitting the goal.</p>
            </section>

            {{-- 2. Mission --}}
            <section id="mission" aria-labelledby="mission-title">
                <p class="eyebrow text-club">The goal of GoalLine</p>
                <h2 id="mission-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Goals completed, not goals intended</h2>
                <p class="mt-3">People finish what matters when they have a real plan, a daily process, and friends who see their progress. A life is built from goals completed, not goals intended.</p>
                <div class="mt-5 rounded-2xl border-l-4 border-club bg-mint px-4 py-3">
                    <p class="font-serif text-lg font-bold tracking-tight text-club">Trust the process.</p>
                    <p class="mt-1 text-ink/80">Doing the right things each day, consistently, is what builds the goal. GoalLine scores the process, so every day on the line counts.</p>
                </div>
                <p class="mt-4">We measure success by goals completed, not by time spent in the app. Check in, then get back to your life.</p>
            </section>

            {{-- 3. Why --}}
            <section id="why" aria-labelledby="why-title">
                <p class="eyebrow text-club">Why it exists</p>
                <h2 id="why-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Progress needs witnesses</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                    <li class="rounded-2xl border border-line bg-white px-4 py-4"><p class="font-semibold text-ink">Tracking alone</p><p class="mt-1 text-sm">Has no witnesses, so it's easy to quietly let a week slide.</p></li>
                    <li class="rounded-2xl border border-line bg-white px-4 py-4"><p class="font-semibold text-ink">Habit leagues</p><p class="mt-1 text-sm">Score generic checkboxes, not the goal you're working toward.</p></li>
                    <li class="rounded-2xl border border-club/30 bg-mint px-4 py-4"><p class="font-semibold text-club">GoalLine</p><p class="mt-1 text-sm text-ink/80">Puts real stakes and friendly accountability behind your own plan for your own goal.</p></li>
                </ul>
            </section>

            {{-- 4. How it was made --}}
            <section id="made" aria-labelledby="made-title">
                <p class="eyebrow text-club">How it was created</p>
                <h2 id="made-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Made at Catholic University</h2>
                <p class="mt-3">GoalLine was built by Ryan Kuegler, a senior at The Catholic University of America&rsquo;s Busch School of Business, as an entry in the CUA AI Vibe Coding Contest. It was built with AI-assisted coding in Claude Code, on Laravel, and runs on Amazon Web Services. Inside the app, Anthropic&rsquo;s Claude writes the goal-specific intake questions and drafts plans.</p>
                <p class="mt-3 text-sm text-ink/70">It&rsquo;s a young project in a private beta, and it gets better with every note from the people using it.</p>
            </section>

            {{-- 5. Impact --}}
            <section id="impact" aria-labelledby="impact-title">
                <p class="eyebrow text-club">Why use it</p>
                <h2 id="impact-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">What you get</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['A plan, not a blank page', 'A tailored, dated plan built from your answers about your goal, your time, and your starting point.'],
                        ['Friends in your corner', 'League mates see your progress, cheer your posts, and nudge you when it counts.'],
                        ['A fair fight', 'You compete against your own plan, so a beginner and a veteran can play the same league.'],
                        ['Honest by design', 'Proof photos, Verified check-ins and highlights make progress real, and disputes are calm and simple.'],
                        ['Private by default', 'You choose who sees your locker and each post. We don\'t sell your information or show ads.'],
                        ['Built around the process', 'One clear list for today, and a week you can still win after a slow start.'],
                    ] as [$title, $body])
                        <li class="rounded-2xl border border-line bg-white px-4 py-4">
                            <p class="font-semibold text-ink">{{ $title }}</p>
                            <p class="mt-1 text-sm">{{ $body }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-ink/70">These are the benefits GoalLine is designed for. We haven&rsquo;t measured outcomes yet, so we don&rsquo;t claim numbers.</p>
            </section>

            {{-- 6. How to play --}}
            <section id="how-to-play" aria-labelledby="play-title">
                <p class="eyebrow text-club">How to play</p>
                <h2 id="play-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Seven steps to the line</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ([
                        ['Set a goal', 'Name it, pick a target date, and answer a few questions written for that goal. Skip any you\'re not sure about.'],
                        ['Review and accept your plan', 'The coach drafts your GoalWeeks: a focus, a milestone and daily tasks. Nothing is final until you tap Accept.'],
                        ['Start or join a GoalLeague', 'Bring your goal into a league and invite friends with a link or code. Or keep it solo.'],
                        ['Check in daily', 'Tick off today\'s tasks and habits from Today\'s Gameplan. Add a photo to show your work.'],
                        ['Open your weekly matchup', 'Swipe through the GoalWeek Matchup to see who\'s ahead and what each side needs for the next band.'],
                        ['Read your weekly recap', 'Once a GoalWeek closes, the recap shows the scores, the Player of the Week, and your process.'],
                        ['Finish the season', 'The table crowns a Champion and hands out season awards. To go again, start a new GoalLeague and invite everyone back.'],
                    ] as $index => [$title, $body])
                        <li class="flex gap-4 rounded-2xl border border-line bg-white px-4 py-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-club font-serif font-bold text-ivory" aria-hidden="true">{{ $index + 1 }}</span>
                            <span><span class="block font-semibold text-ink">{{ $title }}</span><span class="mt-0.5 block text-sm">{{ $body }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- 7. Scoring: every number below comes from ScoringRules. --}}
            <section id="scoring" aria-labelledby="scoring-title">
                <p class="eyebrow text-club">Scoring</p>
                <h2 id="scoring-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Scored against your own plan</h2>
                <p class="mt-3">Every GoalWeek has <strong class="text-ink">plan points</strong>: each task (usually 1), each planned habit day (1), and the milestone ({{ $rules->milestonePoints() }}). Your <strong class="text-ink">percent of plan</strong> is the plan points you earn divided by the plan points planned. Bonus tasks, extra habit days and beating a task&rsquo;s number push you past 100%.</p>

                <h3 class="mt-6 font-serif text-xl font-bold tracking-tight text-ink">The four weekly results</h3>
                {{-- The meter: fixed height, widths from the thresholds. --}}
                <div class="mt-4" role="img" aria-label="A line from 0% of plan: {{ collect($bands)->map(fn ($band) => $band['label'].' '.$band['range'])->implode(', ') }}.">
                    <div class="flex h-4 overflow-hidden rounded-full">
                        @foreach ($meter as $segment)
                            <span class="{{ $bandColor($segment['result']) }} h-full" style="width: {{ $segment['width'] }}%"></span>
                        @endforeach
                    </div>
                    <div class="relative mt-1 h-5 text-xs font-semibold text-ink/70" aria-hidden="true">
                        @php $left = 0; @endphp
                        @foreach ($meter as $segment)
                            @if ($segment['marker'] !== null)
                                <span class="absolute -translate-x-1/2 tabular-nums" style="left: {{ $left }}%">{{ $segment['marker'] }}%</span>
                            @endif
                            @php $left += $segment['width']; @endphp
                        @endforeach
                    </div>
                </div>
                <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Weekly results, their percent of plan and line stars</caption>
                        <thead class="bg-ivory text-left text-xs text-ink/70">
                            <tr><th scope="col" class="px-4 py-2 font-semibold">Result</th><th scope="col" class="px-4 py-2 font-semibold">Percent of plan</th><th scope="col" class="px-4 py-2 text-right font-semibold">Line stars</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($bands as $band)
                                <tr data-band="{{ $band['result']->value }}">
                                    <td class="px-4 py-3"><x-goalweek-result :result="$band['result']" /></td>
                                    <td class="px-4 py-3 text-ink tabular-nums">{{ $band['range'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-ink tabular-nums">{{ $band['points'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm">In line under {{ $rules->closeCallBelow() }}% shows as a close call. A Smashed week counts in full only with proof on a check-in that week or your opponent&rsquo;s confirmation; until then it&rsquo;s credited as Past the line.</p>

                <h3 class="mt-8 font-serif text-xl font-bold tracking-tight text-ink">Matchup stars</h3>
                <p class="mt-2">In a GoalLeague you also face one league mate each week. The higher band wins outright. In the same band it&rsquo;s a <strong class="text-ink">photo finish</strong>: the higher credited percent of plan takes it, and exactly equal percents are a tie.</p>
                <ul class="mt-4 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white text-sm">
                    @foreach ($outcomes as $outcome)
                        <li class="flex items-center justify-between gap-3 px-4 py-3" data-outcome="{{ $outcome['outcome']->value }}">
                            <span><span class="font-semibold text-ink">{{ $outcome['label'] }}</span> <span class="block text-ink/70 sm:inline"><span class="hidden sm:inline">&middot; </span>{{ $outcome['when'] }}</span></span>
                            <span class="shrink-0 font-semibold text-ink tabular-nums">{{ $stars($outcome['points']) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm">The photo finish compares credited percents exactly (to the hundredth), so 100.4% beats 99.6%; a tie only when they&rsquo;re truly equal. Plan points held in an open dispute are left out, and a Smashed week still waiting on proof is capped at {{ $rules->unverifiedSmashedCap() }}%. A bye week (no opponent) earns line stars only and isn&rsquo;t a win, draw or loss.</p>

                <h3 class="mt-8 font-serif text-xl font-bold tracking-tight text-ink">Your weekly total</h3>
                <p class="mt-2"><strong class="text-ink">Line stars + matchup stars</strong>, so a GoalWeek is worth <strong class="text-ink" data-week-range>{{ $range['min'] }} to {{ $range['max'] }} stars</strong>. More stars wins the matchup; equal stars is a draw.</p>

                {{-- Weekly stars explained: run through the real scoring functions. --}}
                <div class="mt-5 rounded-2xl border border-club/30 bg-white shadow-sm" data-example>
                    <p class="eyebrow border-b border-line px-5 py-3 text-club">Weekly stars explained</p>
                    <div class="grid gap-px bg-line sm:grid-cols-2">
                        @foreach ([$avery, $sam] as $player)
                            <div class="bg-white px-4 py-3 text-sm">
                                <p class="font-serif text-lg font-bold tracking-tight text-ink">{{ $player['name'] }}</p>
                                <p class="mt-1">Planned {{ $player['tasks'] }} tasks + {{ $player['habitDays'] }} habit days + milestone {{ $player['milestone'] }} = <strong class="text-ink">{{ $player['planned'] }} plan pts</strong></p>
                                <p>Earned {{ $player['tasksDone'] }} tasks + {{ $player['habitDaysDone'] }} habit days + milestone {{ $player['milestone'] }} = <strong class="text-ink">{{ $player['earned'] }} plan pts</strong></p>
                                <p class="mt-2 text-ink">{{ $player['earned'] }} &divide; {{ $player['planned'] }} = <strong>{{ $player['percent'] }}%</strong></p>
                                <div class="mt-2"><x-goalweek-result :result="$player['result']" :close-call="$player['closeCall']" /></div>
                                <p class="mt-2">{{ $player['outcome']->label() }}: {{ $stars($player['matchPoints']) }}</p>
                                <p class="mt-1 font-semibold text-club">Week total: {{ $player['linePoints'] }} + {{ $player['matchPoints'] }} = {{ $stars($player['total']) }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="border-t border-line px-5 py-3 text-sm">
                        @if ($example['photoFinish'])
                            Both landed {{ Str::lower($avery['result']->label()) }}, so it&rsquo;s a photo finish: {{ $sam['percent'] > $avery['percent'] ? $sam['name'] : $avery['name'] }}&rsquo;s higher percent takes it.
                        @endif
                        If {{ $whatIf['avery']['name'] }} had added {{ $whatIf['avery']['bonusDone'] }} bonus session and finished every task ({{ $whatIf['avery']['percent'] }}%, {{ Str::lower($whatIf['avery']['result']->label()) }}), the higher band wins outright: {{ $whatIf['avery']['name'] }} {{ $stars($whatIf['avery']['total']) }}, {{ $whatIf['sam']['name'] }} {{ $stars($whatIf['sam']['total']) }}.
                    </p>
                </div>

                <h3 class="mt-8 font-serif text-xl font-bold tracking-tight text-ink">The league table</h3>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    @foreach ($rules->tableOrder() as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ol>
                <p class="mt-2 text-sm">Your W&ndash;D&ndash;L record: more stars in a matchup is a win (photo finish wins included), equal stars is a draw, and byes don&rsquo;t count. Results go final one day after the GoalWeek ends.</p>

                <h3 class="mt-8 font-serif text-xl font-bold tracking-tight text-ink">Two ways to win a season</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-line bg-white px-4 py-4 text-sm">
                        <p class="font-semibold text-ink">Table champion</p>
                        <p class="mt-1">Top of the table when the season ends. The season awards go out too:</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($rules->seasonAwards() as $award)
                                <li><span class="font-semibold text-ink">{{ $award['name'] }}</span>: {{ $award['description'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-club/30 bg-mint px-4 py-4 text-sm">
                        <p class="font-semibold text-club">Goal achieved</p>
                        <p class="mt-1 text-ink/80">Cross the GoalLine on your goal itself: mark it complete, add a photo and a note, and it goes in your trophy case. That&rsquo;s the win that matters most, whatever the table says.</p>
                        <p class="mt-2 text-ink/80">Ready for another season? Start a new GoalLeague and invite everyone back.</p>
                    </div>
                </div>
            </section>

            {{-- 8. Fair play --}}
            <section id="fair-play" aria-labelledby="fair-title">
                <p class="eyebrow text-club">Fair play</p>
                <h2 id="fair-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Friendly, and fair</h2>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['Range checks', 'A tracked number has to make sense: 0 to 3× the plan. Over 1.5× the plan, or a new personal best, asks "Are you sure?" once.'],
                        ['No backfilling', 'Check-ins are for today or yesterday. A week locks one day after it ends, and future weeks can\'t be checked off early.'],
                        ['Open matchups', 'Your opponent sees your check-ins as a timeline, with times, numbers and Verified checks.'],
                        ['Proof', 'Add a photo, details (a caption, where and when), or both to any check-in. Photos earn the Verified check; details help your league see your process. A Smashed week counts in full only with a photo or your opponent\'s confirmation.'],
                        ['Dispute button', 'Your opponent or the league owner can calmly question a check-in. You settle it with proof or a note; the league owner can rule.'],
                        ['Plan floor and plan lock', 'Code sets a minimum pace for every week, so an easy plan can\'t Smash every week. A week\'s plan locks when it starts.'],
                    ] as [$term, $detail])
                        <div class="rounded-2xl border border-line bg-white px-4 py-4">
                            <dt class="font-semibold text-ink">{{ $term }}</dt>
                            <dd class="mt-1 text-sm">{{ $detail }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 rounded-2xl bg-club px-4 py-3 font-semibold text-ivory">Scoring is done by plain, tested code. The AI never touches a score.</p>
            </section>

            {{-- 9. AI --}}
            <section id="ai" aria-labelledby="ai-title">
                <p class="eyebrow text-club">The AI coach</p>
                <h2 id="ai-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">What the AI does, and never does</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-line bg-white px-4 py-4 text-sm">
                        <p class="font-semibold text-club">It does</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>Ask questions written for your goal</li>
                            <li>Draft your plan, and refine it when you ask. You preview every change and tap Accept.</li>
                            <li>Check edits to your plan and suggest a fix you can take or leave</li>
                            <li>Read plan files you upload, like a syllabus</li>
                            <li>Write a tip after your weekly review, and a short summary of your finished week from numbers code counted</li>
                            <li>Suggest a caption if you ask</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-line bg-white px-4 py-4 text-sm">
                        <p class="font-semibold text-red-700">It never</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>Scores a week or a matchup</li>
                            <li>Decides a dispute</li>
                            <li>Gives medical advice</li>
                            <li>Posts anything for you</li>
                        </ul>
                    </div>
                </div>
                <x-ai-code-split class="mt-3" />
                <p class="mt-3 text-sm text-ink/70">GoalLine plans are general guidance, not medical advice. Check with a doctor if you have health concerns.</p>
            </section>

            {{-- 10. Privacy --}}
            <section id="privacy" aria-labelledby="privacy-title">
                <p class="eyebrow text-club">Privacy and safety</p>
                <h2 id="privacy-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Your data, plainly</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>You choose who sees your locker (only you, your GoalLeagues, or everyone) and who sees each post.</li>
                    <li>Photos are served only to people allowed to see them, with location and camera data removed.</li>
                    <li>The AI coach gets the details it needs to plan your goal, sent to Anthropic.</li>
                    <li>We don&rsquo;t sell your information or use it for advertising.</li>
                    <li>You can delete your account, goals, posts and photos at any time.</li>
                </ul>
                <a href="{{ route('privacy') }}" class="mt-4 inline-flex min-h-11 items-center font-semibold text-club hover:underline">Read Privacy &amp; Safety <span aria-hidden="true" class="ml-1">&rarr;</span></a>
            </section>

            {{-- 11. FAQ --}}
            <section id="faq" aria-labelledby="faq-title">
                <p class="eyebrow text-club">FAQ</p>
                <h2 id="faq-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Questions</h2>
                <div class="mt-4 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ([
                        ['Is it free?', 'Yes. There\'s nothing to buy. GoalLine is in a private beta, so new accounts need an invite code, and anyone can try the demo.'],
                        ['Can I play solo?', 'Yes. After you set a goal, choose "Keep it solo". You still get your plan, Today\'s Gameplan, weekly results and badges, and you can start or join a league any time.'],
                        ['What counts as proof?', 'A photo or screenshot of the work on a checked-off task: JPG, PNG or WebP, up to 5 MB. Location and camera data are removed. You, that week\'s opponent and the league owner can see it.'],
                        ['What if I miss a day?', 'It happens. You can check in for today or yesterday, and missed days stay neutral, never red. Your result is about the whole week, so a strong finish still counts.'],
                        ['Can I change my plan?', 'Yes, for GoalWeeks that haven\'t started: ask the coach with "Refine your plan" and preview the changes, or edit by hand. A week\'s plan locks when it starts, and no week can go below its minimum pace. You can declare up to 2 lighter weeks a season.'],
                        ['Who can see my posts?', 'You choose each time: Proof only (your opponent and the league owner), League, or League + friends. Proof photos are always visible to you, that week\'s opponent and the league owner.'],
                    ] as [$question, $answer])
                        <details class="group">
                            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 font-semibold text-ink hover:bg-ivory focus-visible:bg-ivory [&::-webkit-details-marker]:hidden">
                                {{ $question }}
                                <span class="text-xl text-club transition group-open:rotate-45" aria-hidden="true">+</span>
                            </summary>
                            <p class="px-5 pb-4 text-sm">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- 12. Contact --}}
            <section id="contact" aria-labelledby="contact-title">
                <p class="eyebrow text-club">Contact and feedback</p>
                <h2 id="contact-title" class="mt-2 font-serif text-3xl font-bold tracking-tight text-ink">Something broken, confusing, or great?</h2>
                @if ($viewer)
                    <form method="POST" action="{{ route('feedback.store') }}" class="mt-4 rounded-2xl border border-line bg-white px-4 py-3">
                        @csrf
                        <input type="hidden" name="page" value="/about">
                        <label for="about-feedback" class="text-sm font-semibold text-ink">Tell the GoalLine team</label>
                        <textarea id="about-feedback" name="body" rows="4" required maxlength="2000" class="mt-2 block w-full rounded-xl border border-line bg-ivory/60 px-3 py-2 text-sm text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">{{ old('body') }}</textarea>
                        @error('body')
                            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="mt-3 min-h-11 rounded-full bg-club px-6 py-2 font-semibold text-ivory hover:bg-club-dark">Send feedback</button>
                    </form>
                @else
                    <p class="mt-3">Beta testers can use the Feedback button inside the app.{{ $contactEmail ? ' Or email us:' : ' New here? Try the demo first.' }}</p>
                @endif
                @if ($contactEmail)
                    <p class="mt-3 text-sm">Email: <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('GoalLine feedback') }}" class="font-semibold text-club hover:underline">{{ $contactEmail }}</a></p>
                @endif
            </section>
        </div>

        @unless ($viewer)
            <div class="mt-14 rounded-2xl bg-club px-6 py-8 text-center">
                <p class="font-serif text-2xl font-bold tracking-tight text-ivory">See it for yourself.</p>
                <p class="mt-2 text-ivory/90">One click, no sign-up: a pretend world with friends and a live matchup.</p>
                @if (config('goalline.demo.enabled'))
                    <a href="{{ route('demo') }}" class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-full bg-gold px-6 py-2.5 font-semibold text-ink transition hover:brightness-105">Try the demo <span aria-hidden="true">&rarr;</span></a>
                @endif
            </div>
        @endunless
    </div>
</x-dynamic-component>

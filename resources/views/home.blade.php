<x-layouts.field title="GoalLine · Get in line with your goals">
    <x-slot:nav>
        @auth
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ivory/90 hover:text-ivory">Clubhouse</a>
        @else
            <a href="{{ route('login') }}" class="text-sm font-medium text-ivory/90 hover:text-ivory">Log in</a>
        @endauth
    </x-slot:nav>

    <x-slot:hero>
        <div class="mx-auto max-w-3xl px-4 pt-14 pb-24 text-center sm:pt-20 sm:pb-32">
            <p class="eyebrow text-mint">Get in line.</p>
            <h1 class="mt-5 font-serif text-5xl font-medium tracking-tight text-balance text-ivory sm:text-7xl">
                Get in line with your goals.
            </h1>
            <p class="mt-5 font-serif text-xl text-ivory/90 sm:text-2xl">Stay on track for the life you want.</p>
            <p class="mx-auto mt-6 max-w-xl text-ivory/85">
                GoalLine turns what matters to you into a dated, personal plan, shows you today's next step, and keeps the people around you in your corner.
            </p>

            <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-ivory px-7 py-3 font-semibold text-club shadow-lg shadow-black/10 transition hover:bg-chalk">
                        Go to your clubhouse
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-full bg-ivory px-7 py-3 font-semibold text-club shadow-lg shadow-black/10 transition hover:bg-chalk">
                        {{ config('goalline.beta.invite_only') ? 'Have an invite? Join the beta' : 'Get started' }}
                    </a>
                    <a href="{{ route('login') }}" class="rounded-full border border-ivory/70 px-7 py-3 font-semibold text-ivory transition hover:border-ivory hover:bg-ivory/10">
                        Log in
                    </a>
                @endauth
            </div>
        </div>
    </x-slot:hero>

    <section class="mx-auto max-w-5xl px-4 py-16 sm:py-20">
        <div class="text-center">
            <p class="eyebrow text-club">How it works</p>
            <h2 class="mt-3 font-serif text-3xl font-medium tracking-tight text-club sm:text-4xl">From ambition to achievement</h2>
        </div>

        <ol class="mt-12 grid overflow-hidden rounded-2xl border border-line bg-white sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Define what matters', 'An exam, a thesis, a race, a career move. Name the goal and the date it needs to happen.'],
                ['Get your roadmap', 'A personalized, dated plan built for your kind of goal, so the path is clear from day one.'],
                ['Know today\'s step', 'Every morning, see exactly what to do next. When life gets in the way, your plan adapts.'],
                ['Stay accountable', 'Check in with friends or teammates in a friendly league. Optional, encouraging, and fun.'],
            ] as [$heading, $body])
                <li class="border-line p-7 max-sm:not-last:border-b sm:max-lg:odd:border-r sm:max-lg:nth-[-n+2]:border-b lg:not-last:border-r">
                    <span class="font-serif text-3xl font-medium text-grass tabular-nums">0{{ $loop->iteration }}</span>
                    <h3 class="mt-4 font-semibold text-ink">{{ $heading }}</h3>
                    <p class="mt-2 text-[0.95rem] leading-relaxed text-ink/70">{{ $body }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Brand feature: the league is the mechanism, not the point. --}}
    <section class="mx-auto max-w-5xl px-4 pb-20">
        <div class="relative overflow-hidden rounded-3xl bg-club px-8 py-14 text-center sm:px-16">
            <x-field-backdrop class="absolute inset-0 size-full" />
            <div class="relative">
                <p class="eyebrow text-mint">Our philosophy</p>
                <h2 class="mx-auto mt-4 max-w-2xl font-serif text-3xl font-medium tracking-tight text-balance text-ivory sm:text-4xl">
                    The league is the mechanism, not the point.
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-ivory/85">
                    Finish last in your league and still reach your goal? You won. GoalLine measures success by what you accomplish, not where you rank.
                </p>
            </div>
        </div>
    </section>
</x-layouts.field>

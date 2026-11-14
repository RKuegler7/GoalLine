{{-- The public landing page, built for a five-second first impression on a phone: the lockup with the Line drawing in,
     the headline and one sentence, "Try the demo" (no sign-up) and "Log in", real screens from the demo in phone frames
     (made by qa/e2e/landing-shots.spec.js), three steps, and the AI/code split. --}}
@php
    $demo = config('goalline.demo.enabled');
    $screens = [
        ['matchup', 'Matchup', 'You against one opponent, each against your own plan.', 'The Matchup screen: two players side by side, each with their percent of plan and band for the week.'],
        ['standings', 'Standings', 'One table. Stars rank it; swipe for the rest.', 'The Standings table: rank, player, stars, record and line stars for a GoalLeague.'],
        ['plan', 'Your plan', 'Week by week, built for your goal by the AI coach.', 'A goal page with this week\'s tasks and the plan for the weeks ahead.'],
    ];
@endphp

<x-layouts.field title="GoalLine · Get in line with your goals" :header-logo="false"
    description="Compete weekly with friends, each against your own AI-built plan. AI builds the plan; plain, tested code keeps score. Try the demo, no sign-up needed.">
    <x-slot:nav>
        @auth
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-ivory/90 hover:text-ivory">Clubhouse</a>
        @else
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-ivory/90 hover:text-ivory">Log in</a>
        @endauth
    </x-slot:nav>

    <x-slot:hero>
        <div class="mx-auto max-w-3xl px-4 pt-2 pb-14 text-center sm:pt-10 sm:pb-20" data-landing-hero>
            <x-brand.logo animated class="mx-auto h-11 sm:h-14" />
            <h1 class="mt-6 font-serif text-[2.5rem] leading-[1.05] font-bold tracking-tight text-balance text-ivory sm:text-6xl">
                Get in line with your goals.
            </h1>
            <p class="mx-auto mt-4 max-w-md text-[17px] leading-snug text-ivory/90 sm:text-lg">
                Compete weekly with friends, each against your own AI-built plan.
            </p>

            <div class="mx-auto mt-7 flex max-w-sm flex-col gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-14 items-center justify-center rounded-full bg-gold px-7 text-[17px] font-bold text-ink shadow-lg shadow-black/15 transition hover:brightness-105">
                        Go to your Clubhouse
                    </a>
                @else
                    @if ($demo)
                        <a href="{{ route('demo') }}" data-try-demo class="inline-flex min-h-14 items-center justify-center rounded-full bg-gold px-7 text-[17px] font-bold text-ink shadow-lg shadow-black/15 transition hover:brightness-105">
                            Try the demo &rarr;
                        </a>
                        <p class="-mt-1 text-[13px] text-ivory/85">No sign-up needed. A pretend account mid-season, with friends and a live Matchup.</p>
                    @else
                        <a href="{{ route('register') }}" class="inline-flex min-h-14 items-center justify-center rounded-full bg-gold px-7 text-[17px] font-bold text-ink shadow-lg shadow-black/15 transition hover:brightness-105">
                            {{ config('goalline.beta.invite_only') ? 'Have an invite? Join the beta' : 'Get started' }}
                        </a>
                    @endif
                    <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-ivory/70 px-7 font-semibold text-ivory transition hover:border-ivory hover:bg-ivory/10">
                        Log in
                    </a>
                    @if ($demo)
                        <a href="{{ route('register') }}" class="mx-auto inline-flex min-h-11 items-center text-[13px] font-semibold text-mint underline-offset-2 hover:underline">
                            {{ config('goalline.beta.invite_only') ? 'Have an invite? Join the beta' : 'New here? Create an account' }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </x-slot:hero>

    {{-- Real screens from the demo. A swipeable row on phones, side by side on wider screens. --}}
    <section aria-labelledby="screens-title" class="mx-auto max-w-5xl pt-10 sm:pt-14">
        <h2 id="screens-title" class="sr-only">What it looks like</h2>
        <ul class="flex snap-x snap-mandatory gap-5 overflow-x-auto px-[max(1rem,calc(50%-8rem))] pb-4 [scrollbar-width:none] sm:justify-center sm:overflow-visible sm:px-4" data-landing-screens tabindex="0" aria-label="Screens from the app. On a phone, swipe sideways for more.">
            @foreach ($screens as [$file, $label, $caption, $alt])
                <li class="w-52 shrink-0 snap-center text-center">
                    <div class="rounded-[2rem] bg-ink p-1.5 shadow-xl shadow-ink/20">
                        <img src="{{ asset('images/landing/'.$file.'.webp') }}" width="600" height="1298" alt="{{ $alt }}"
                            @if (! $loop->first) loading="lazy" @endif decoding="async" class="block h-auto w-full rounded-[1.6rem] bg-ivory">
                    </div>
                    <p class="mt-3 font-serif text-[17px] font-bold text-club">{{ $label }}</p>
                    <p class="mt-0.5 text-[13px] leading-snug text-ink/75">{{ $caption }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section aria-labelledby="how-title" class="mx-auto max-w-5xl px-4 py-12 sm:py-16">
        <h2 id="how-title" class="text-center font-serif text-[28px] font-bold tracking-tight text-club sm:text-4xl">How it works</h2>
        <ol class="mt-8 grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['Set a goal', 'An exam, a race, a thesis, a career move. Name it and the date it needs to happen.'],
                ['Get your plan', 'The AI coach asks a few questions and drafts a week-by-week plan. Code checks the pace.'],
                ['Play your week', 'Check in as you go. Each week you face a friend, each measured against your own plan.'],
            ] as [$heading, $body])
                <li class="flex gap-4 rounded-2xl border border-line bg-white p-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-mint font-serif text-lg font-bold text-club tabular-nums" aria-hidden="true">{{ $loop->iteration }}</span>
                    <div>
                        <h3 class="font-semibold text-ink"><span class="sr-only">Step {{ $loop->iteration }}: </span>{{ $heading }}</h3>
                        <p class="mt-1 text-[15px] leading-relaxed text-ink/75">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section aria-labelledby="fair-title" class="mx-auto max-w-5xl px-4 pb-16">
        <div class="relative overflow-hidden rounded-3xl bg-club px-6 py-10 text-center sm:px-16 sm:py-14">
            <x-field-backdrop class="absolute inset-0 size-full" />
            <div class="relative">
                <h2 id="fair-title" class="mx-auto max-w-2xl font-serif text-[28px] leading-tight font-bold tracking-tight text-balance text-ivory sm:text-4xl">
                    AI builds the plan. Code keeps score.
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-ivory/90">
                    Plain, tested code scores every week the same way for everyone. AI never scores your week or decides disputes.
                </p>
                <a href="{{ route('about') }}#scoring" class="mt-5 inline-flex min-h-11 items-center font-semibold text-mint underline-offset-2 hover:underline">How scoring works &rarr;</a>
            </div>
        </div>
    </section>
</x-layouts.field>

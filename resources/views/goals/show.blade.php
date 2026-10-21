<x-layouts.app :title="$goal->title.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Clubhouse</a>
            <p class="eyebrow mt-6 text-mint">{{ $goal->category->label() }}</p>
            <div class="mt-3 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <h1 class="font-serif text-4xl font-medium tracking-tight text-balance text-ivory sm:text-5xl">{{ $goal->title }}</h1>
                @if ($goal->isActive())
                    <a href="{{ route('goals.edit', $goal) }}" class="shrink-0 self-start rounded-full border border-ivory/60 px-5 py-2 text-sm font-semibold text-ivory transition hover:border-ivory hover:bg-ivory hover:text-club sm:self-auto">
                        Edit goal
                    </a>
                @else
                    <span class="eyebrow shrink-0 self-start rounded-full bg-ivory/15 px-4 py-2 text-[0.7rem] text-ivory sm:self-auto">Retired</span>
                @endif
            </div>
        </div>
    </x-slot:hero>

    @unless ($goal->isActive())
        <p class="mb-6 rounded-xl border border-line bg-white px-4 py-3 text-sm text-ink/70">
            You retired this goal on {{ $goal->retired_at?->format('F j, Y') }}. Its roadmap is kept here as part of your history.
        </p>
    @endunless

    <dl class="grid overflow-hidden rounded-2xl border border-line bg-white sm:grid-cols-2">
        <div class="border-line px-6 py-5 max-sm:border-b sm:border-r">
            <dt class="eyebrow text-club">Target date</dt>
            <dd class="mt-2 font-serif text-2xl font-medium text-club">{{ $goal->target_date->format('F j, Y') }}</dd>
        </div>
        <div class="px-6 py-5">
            <dt class="eyebrow text-club">Time left</dt>
            <dd class="mt-2 font-serif text-2xl font-medium text-club">
                <x-days-left :days="$goal->daysRemaining()" />
            </dd>
        </div>
    </dl>

    @if ($goal->why)
        <section class="mt-6 rounded-2xl border border-line bg-white px-6 py-5">
            <h2 class="eyebrow text-club">Why it matters</h2>
            <p class="mt-2 font-serif text-xl leading-relaxed text-ink">&ldquo;{{ $goal->why }}&rdquo;</p>
        </section>
    @endif

    <x-plan-materials :goal="$goal" />

    <x-roadmap :goal="$goal" />

    @if ($goal->isActive())
        <div class="mt-10 flex flex-col gap-2 border-t border-line pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-ink/60">
                @if ($goal->inTrial())
                    <span class="font-semibold text-club">Trial:</span> you can remove this goal until {{ $goal->lockedInAt()->format('F j') }}. After that, you're locked in.
                @else
                    <span class="font-semibold text-club">Locked in</span> since {{ $goal->lockedInAt()->format('F j') }}.
                @endif
            </p>
            <a href="{{ route('goals.leave', $goal) }}" class="font-semibold text-ink/60 hover:text-red-700">
                {{ $goal->inTrial() ? 'Remove goal' : 'Retire goal' }}
            </a>
        </div>
    @endif
</x-layouts.app>

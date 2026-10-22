<x-layouts.app :title="$goal->title.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Clubhouse</a>
            <p class="eyebrow mt-6 text-mint">{{ $goal->category->label() }}</p>
            <div class="mt-3 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <h1 class="font-serif text-4xl font-medium tracking-tight text-balance text-ivory sm:text-5xl">{{ $goal->title }}</h1>
                @if ($goal->isActive())
                    <div class="flex shrink-0 gap-2 self-start sm:self-auto">
                        <a href="{{ route('goals.complete', $goal) }}" class="rounded-full bg-ivory px-5 py-2 text-sm font-semibold text-club transition hover:bg-white">
                            I did it
                        </a>
                        <a href="{{ route('goals.edit', $goal) }}" class="rounded-full border border-ivory/60 px-5 py-2 text-sm font-semibold text-ivory transition hover:border-ivory hover:bg-ivory hover:text-club">
                            Edit goal
                        </a>
                    </div>
                @elseif ($goal->isCompleted())
                    <span class="eyebrow shrink-0 self-start rounded-full bg-gold px-4 py-2 text-[0.7rem] text-ink sm:self-auto">Completed</span>
                @else
                    <span class="eyebrow shrink-0 self-start rounded-full bg-ivory/15 px-4 py-2 text-[0.7rem] text-ivory sm:self-auto">Retired</span>
                @endif
            </div>
        </div>
    </x-slot:hero>

    @if ($goal->isCompleted())
        {{-- Goal completion celebration. --}}
        <section class="mb-6 overflow-hidden rounded-2xl border border-line bg-white">
            <div class="relative overflow-hidden bg-club">
                <x-field-backdrop class="absolute inset-0 size-full" />
                <div class="relative grid gap-6 px-6 py-10 sm:grid-cols-[1fr_auto] sm:items-center sm:px-10">
                    <div>
                        <p class="eyebrow text-gold">Goal complete</p>
                        <h2 class="mt-3 font-serif text-3xl font-medium tracking-tight text-ivory sm:text-4xl">You crossed the GoalLine.</h2>
                        <p class="mt-3 text-ivory/85">
                            {{ App\Clock::local($goal->completed_at)->format('F j, Y') }} &middot; {{ $goal->points() }} {{ Str::plural('point', $goal->points()) }} earned along the way
                        </p>
                        <a href="{{ route('highlights.create', ['goal' => $goal]) }}" class="mt-5 inline-flex items-center gap-2 rounded-full bg-gold px-5 py-2.5 text-sm font-semibold text-ink transition hover:brightness-105">
                            <x-icon name="highlights" class="size-4" /> Share to Highlights
                        </a>
                    </div>
                    @if ($goal->completionPhotoUrl())
                        <img src="{{ $goal->completionPhotoUrl() }}" alt="Photo from completing {{ $goal->title }}" class="aspect-square w-full max-w-56 rounded-xl object-cover ring-4 ring-ivory/30">
                    @endif
                </div>
            </div>
            <x-goal-line />
            @if ($goal->completion_note)
                <p class="px-6 py-5 font-serif text-xl leading-relaxed text-ink sm:px-10">&ldquo;{{ $goal->completion_note }}&rdquo;</p>
            @endif
        </section>
    @elseif (! $goal->isActive())
        <p class="mb-6 rounded-xl border border-line bg-white px-4 py-3 text-sm text-ink/70">
            You retired this goal on {{ App\Clock::local($goal->retired_at)?->format('F j, Y') }}. Its roadmap is kept here as part of your history.
        </p>
    @endif

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

    <x-habits-panel :goal="$goal" />

    <x-roadmap :goal="$goal" />

    @if ($goal->isActive())
        <div class="mt-10 flex flex-col gap-2 border-t border-line pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-ink/60">
                @if ($goal->inTrial())
                    <span class="font-semibold text-club">Trial:</span> you can remove this goal until {{ App\Clock::local($goal->lockedInAt())->format('F j') }}. After that, you're locked in.
                @else
                    <span class="font-semibold text-club">Locked in</span> since {{ App\Clock::local($goal->lockedInAt())->format('F j') }}.
                @endif
            </p>
            <a href="{{ route('goals.leave', $goal) }}" class="font-semibold text-ink/60 hover:text-red-700">
                {{ $goal->inTrial() ? 'Remove goal' : 'Retire goal' }}
            </a>
        </div>
    @endif
</x-layouts.app>

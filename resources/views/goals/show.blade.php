<x-layouts.app :title="$goal->title.' · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$goal->title" :back="route('dashboard')" back-label="Clubhouse" :eyebrow="$goal->category->label()">
            @if ($goal->isActive())
                <a href="{{ route('goals.complete', $goal) }}" class="gl-tap relative inline-flex min-h-9 items-center rounded-full bg-ivory px-3 text-[13px] font-semibold text-club transition hover:bg-white">I did it</a>
                <a href="{{ route('goals.edit', $goal) }}" class="gl-tap relative inline-flex min-h-9 items-center rounded-full bg-ivory/15 px-3 text-[13px] font-semibold text-ivory transition hover:bg-ivory/25">Edit goal</a>
            @elseif ($goal->isCompleted())
                <span class="eyebrow rounded-full bg-gold px-2.5 py-1 text-[11px] text-ink">Completed</span>
            @else
                <span class="eyebrow rounded-full bg-ivory/15 px-2.5 py-1 text-[11px] text-ivory">Retired</span>
            @endif
        </x-page-header>
    </x-slot:hero>

    @if ($goal->isCompleted())
        {{-- Goal completion celebration. --}}
        <section class="mb-6 overflow-hidden rounded-2xl border border-line bg-white">
            <div class="relative overflow-hidden bg-club">
                <x-field-backdrop class="absolute inset-0 size-full" />
                <div class="relative grid gap-6 px-6 py-10 sm:grid-cols-[1fr_auto] sm:items-center sm:px-10">
                    <div>
                        <p class="eyebrow text-gold-light">Goal complete</p>
                        <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight text-ivory sm:text-4xl">You crossed the GoalLine.</h2>
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
                <p class="px-4 py-3 font-serif text-xl leading-relaxed text-ink sm:px-10 tracking-tight">&ldquo;{{ $goal->completion_note }}&rdquo;</p>
            @endif
        </section>
    @elseif (! $goal->isActive())
        <p class="mb-6 rounded-xl border border-line bg-white px-4 py-3 text-sm text-ink/70">
            You retired this goal on {{ App\Clock::local($goal->retired_at)?->format('F j, Y') }}. Its roadmap is kept here as part of your history.
        </p>
    @endif

    <dl class="grid grid-cols-2 overflow-hidden rounded-xl border border-line bg-white" data-first-content>
        <div class="border-r border-line px-3 py-2">
            <dt class="text-[11px] font-semibold text-ink/70">Target date</dt>
            <dd class="text-[15px] font-bold text-club">{{ $goal->target_date->format('M j, Y') }}</dd>
        </div>
        <div class="px-3 py-2">
            <dt class="text-[11px] font-semibold text-ink/70">Time left</dt>
            <dd class="text-[15px] font-bold text-club"><x-days-left :days="$goal->daysRemaining()" /></dd>
        </div>
    </dl>

    @if ($goal->why)
        <details class="group mt-2 rounded-xl border border-line bg-white">
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 px-3 py-1.5 [&::-webkit-details-marker]:hidden">
                <span class="eyebrow shrink-0 text-[11px] text-club">Why it matters</span>
                <span class="min-w-0 flex-1 truncate text-[13px] text-ink/80 group-open:hidden">&ldquo;{{ $goal->why }}&rdquo;</span>
                <x-icon name="chevron-down" class="ml-auto size-4 shrink-0 text-ink/70 transition group-open:rotate-180" />
            </summary>
            <p class="border-t border-line px-3 py-2 text-[15px] leading-relaxed text-ink">&ldquo;{{ $goal->why }}&rdquo;</p>
        </details>
    @endif

    <x-intake-summary :goal="$goal" />

    <x-plan-materials :goal="$goal" />

    <x-habits-panel :goal="$goal" />

    <x-roadmap :goal="$goal" />

    @can('update', $goal)
        @if ($goal->weeks->isNotEmpty())
            <x-ai-code-split :goal="$goal" collapsed class="mt-6" />
        @endif
    @endcan

    @if ($goal->isActive())
        <div class="mt-6 flex flex-col gap-2 border-t border-line pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-ink/70">
                @if ($goal->inTrial())
                    <span class="font-semibold text-club">Trial:</span> you can remove this goal until {{ App\Clock::local($goal->lockedInAt())->format('F j') }}. After that, you're locked in.
                @else
                    <span class="font-semibold text-club">Locked in</span> since {{ App\Clock::local($goal->lockedInAt())->format('F j') }}.
                @endif
            </p>
            <a href="{{ route('goals.leave', $goal) }}" class="font-semibold text-ink/70 hover:text-red-700">
                {{ $goal->inTrial() ? 'Remove goal' : 'Retire goal' }}
            </a>
        </div>
    @endif
</x-layouts.app>

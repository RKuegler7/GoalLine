<x-layouts.app title="Clubhouse · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Your clubhouse</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">
                Welcome, {{ Str::before(auth()->user()->name, ' ') }}.
            </h1>
            <p class="mt-3 max-w-xl text-ivory/85">Let's set the goal you're working toward. Everything else builds from there.</p>
        </div>
    </x-slot:hero>

    {{-- Primary: the user's own goal. --}}
    <section class="overflow-hidden rounded-2xl border border-line bg-white">
        <div class="relative h-36 overflow-hidden bg-club">
            <x-field-backdrop class="absolute inset-0 size-full" />
        </div>
        <x-goal-line />
        <div class="px-8 py-10 text-center">
            <p class="eyebrow text-club">Your goal</p>
            <h2 class="mt-3 font-serif text-2xl font-medium text-club">What do you want to accomplish?</h2>
            <p class="mx-auto mt-2 max-w-md text-ink/70">
                Name a goal and its date, and GoalLine will build your personal roadmap. Goal setup is coming next.
            </p>
            <button type="button" disabled class="mt-6 cursor-not-allowed rounded-full bg-club/40 px-6 py-2.5 font-semibold text-ivory">
                Set your first goal
            </button>
        </div>
    </section>

    {{-- Secondary: daily execution, pace, and the optional league. --}}
    <dl class="mt-6 grid overflow-hidden rounded-2xl border border-line bg-white sm:grid-cols-3">
        @foreach ([
            ['Today', 'No steps yet', 'Your daily steps appear once your roadmap is ready.'],
            ['On pace', '—', 'We\'ll tell you if you\'re in line for your deadline.'],
            ['League', 'Optional', 'Join friends for encouragement and friendly accountability.'],
        ] as [$label, $value, $note])
            <div class="border-line px-6 py-5 max-sm:not-last:border-b sm:not-last:border-r">
                <dt class="eyebrow text-club">{{ $label }}</dt>
                <dd class="mt-2 font-serif text-2xl font-medium text-club">{{ $value }}</dd>
                <dd class="mt-1 text-sm text-ink/60">{{ $note }}</dd>
            </div>
        @endforeach
    </dl>
</x-layouts.app>

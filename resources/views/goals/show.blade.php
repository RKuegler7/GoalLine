<x-layouts.app :title="$goal->title.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Clubhouse</a>
            <p class="eyebrow mt-6 text-mint">{{ $goal->category->label() }}</p>
            <div class="mt-3 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <h1 class="font-serif text-4xl font-medium tracking-tight text-balance text-ivory sm:text-5xl">{{ $goal->title }}</h1>
                <a href="{{ route('goals.edit', $goal) }}" class="shrink-0 self-start rounded-full border border-ivory/60 px-5 py-2 text-sm font-semibold text-ivory transition hover:border-ivory hover:bg-ivory hover:text-club sm:self-auto">
                    Edit goal
                </a>
            </div>
        </div>
    </x-slot:hero>

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

    <x-roadmap :goal="$goal" />
</x-layouts.app>

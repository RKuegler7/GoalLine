<x-layouts.app :title="'Join '.$challenge->title.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">You&rsquo;re invited to a challenge</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $challenge->title }}</h1>
            @if ($challenge->description)
                <p class="mt-3 max-w-xl whitespace-pre-line text-ivory/85">{{ $challenge->description }}</p>
            @endif
        </div>
    </x-slot:hero>

    <section class="mx-auto max-w-xl rounded-2xl border border-line bg-white p-6 text-center sm:p-8">
        <p class="text-ink/75">
            {{ $challenge->starts_on->format('M j') }}&ndash;{{ $challenge->ends_on->format('M j') }} &middot;
            check in on {{ $challenge->target_days }} of {{ $challenge->lengthInDays() }} days to finish &middot;
            started by {{ $challenge->creator->name }} &middot;
            {{ $challenge->members_count }} {{ Str::plural('member', $challenge->members_count) }} so far
        </p>
        @if ($challenge->isOver())
            <p class="mt-5 font-semibold text-ink/60">This challenge is over.</p>
        @else
            <form method="POST" action="{{ route('challenges.join.store', $challenge->invite_code) }}" class="mt-6">
                @csrf
                <button type="submit" class="rounded-full bg-club px-8 py-3 font-semibold text-ivory hover:bg-club-dark">I&rsquo;m in</button>
            </form>
        @endif
    </section>
</x-layouts.app>

<x-layouts.app title="Highlights · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Highlights</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Celebrate the crossings</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Finished goals, great GoalWeeks, and milestones from the people you follow and your GoalLeague mates.</p>
        </div>
    </x-slot:hero>

    <div class="grid gap-8 lg:grid-cols-[1fr_18rem]">
        <div class="min-w-0">
            @if ($highlights->isEmpty())
                <section class="overflow-hidden rounded-2xl border border-line bg-white">
                    <div class="relative h-32 overflow-hidden bg-club">
                        <x-field-backdrop class="absolute inset-0 size-full" />
                    </div>
                    <x-goal-line />
                    <div class="px-8 py-8 text-center">
                        <h2 class="font-serif text-2xl font-medium text-club">Nothing here yet</h2>
                        <p class="mx-auto mt-2 max-w-md text-ink/70">
                            Follow friends or join a GoalLeague to see their highlights. When you finish a goal, have a great GoalWeek, or hit a milestone, you'll get a button to share it here.
                        </p>
                    </div>
                </section>
            @else
                <div class="space-y-5">
                    @foreach ($highlights as $highlight)
                        <x-highlight-card :highlight="$highlight" />
                    @endforeach
                </div>
                @if ($hasMore)
                    <div class="mt-6 text-center">
                        <a href="{{ route('highlights.index', ['before' => $highlights->last()->id]) }}" class="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-club hover:border-club/40">Load older highlights</a>
                    </div>
                @endif
            @endif
        </div>

        <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <form method="GET" action="{{ route('people.index') }}" class="rounded-2xl border border-line bg-white px-5 py-4">
                <label for="people-search" class="eyebrow text-club">Find people</label>
                <div class="mt-2 flex gap-2">
                    <input id="people-search" name="q" placeholder="Name or @username" class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    <button type="submit" class="rounded-lg bg-club px-3 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Search</button>
                </div>
            </form>

            <section class="rounded-2xl border border-line bg-white px-5 py-4">
                <h2 class="eyebrow text-club">Friends</h2>
                @forelse ($friends as $friend)
                    <a href="{{ $friend->profileUrl() }}" class="mt-3 flex items-center gap-2.5 text-sm text-ink hover:text-club">
                        <x-avatar :user="$friend" class="size-8 text-[0.6rem]" />
                        <span class="truncate font-medium">{{ $friend->name }}</span>
                    </a>
                @empty
                    <p class="mt-2 text-sm text-ink/60">Friends are people you follow who follow you back.</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.app>

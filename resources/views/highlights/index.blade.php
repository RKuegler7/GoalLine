<x-layouts.app title="Around the League · GoalLine">
    <x-slot:hero>
        <x-page-header title="Around the League" eyebrow="Highlights" subtitle="Check-ins, finished goals and great GoalWeeks from your people.">
            <a href="{{ route('friends.index') }}" class="gl-tap relative inline-flex min-h-9 items-center gap-1 rounded-full bg-ivory/15 px-3 text-[13px] font-semibold text-ivory hover:bg-ivory/25"><x-icon name="users" class="size-4" /> Friends</a>
        </x-page-header>
    </x-slot:hero>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="min-w-0">
            @if ($highlights->isEmpty())
                <x-empty-state title="Your Highlights start here" message="Show your work on a checked-off task, or share a great GoalWeek. Friends’ and league mates’ posts land here too." action="Post a highlight" :href="route('highlights.create')" />
            @else
                <div class="space-y-5">
                    @foreach ($highlights as $highlight)
                        <x-highlight-card :highlight="$highlight" />
                    @endforeach
                </div>
                @if ($hasMore)
                    <div class="mt-6 text-center">
                        <a href="{{ route('highlights.index', ['before' => $highlights->last()->id]) }}" class="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-club hover:border-club/40">Load older posts</a>
                    </div>
                @endif
            @endif
        </div>

        <aside class="hidden space-y-4 lg:sticky lg:top-20 lg:block lg:self-start">
            <x-people-search-form label="Find people" />

            <form method="POST" action="{{ route('friends.store') }}" data-live class="rounded-2xl border border-line bg-white px-4 py-3">
                @csrf
                <label for="friend-who" class="eyebrow text-club">Add a friend</label>
                <div class="mt-2 flex gap-2">
                    <input id="friend-who" name="who" required autocomplete="off" autocapitalize="none" placeholder="Exact @username or email" class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    <button type="submit" class="rounded-lg bg-club px-3 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Send</button>
                </div>
                @error('who')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </form>

            <section class="rounded-2xl border border-line bg-white px-4 py-3">
                <div class="flex items-baseline justify-between">
                    <h2 class="eyebrow text-club">Friends</h2>
                    <a href="{{ route('friends.index') }}" class="text-xs font-semibold text-club hover:underline">Manage</a>
                </div>
                @forelse ($friends as $friend)
                    <a href="{{ $friend->profileUrl() }}" class="mt-3 flex items-center gap-2.5 text-sm text-ink hover:text-club">
                        <x-avatar :user="$friend" class="size-8 text-[11px]" />
                        <span class="truncate font-medium">{{ $friend->name }}</span>
                    </a>
                @empty
                    <x-empty-state class="mt-2" message="Add friends by their exact username or email. They’ll see the posts you share with friends, and you’ll see theirs." action="Find people" :href="route('people.search')" />
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.app>

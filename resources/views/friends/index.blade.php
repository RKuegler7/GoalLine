<x-layouts.app title="Friends · GoalLine">
    <x-slot:hero>
        <x-page-header title="Friends" :back="route('highlights.index')" back-label="Highlights" subtitle="Friends cheer, nudge and see the posts you share with friends.">
            <a href="{{ route('people.search') }}" class="gl-tap relative inline-flex min-h-9 items-center gap-1 rounded-full bg-ivory px-3 text-[13px] font-semibold text-club hover:bg-white"><x-icon name="search" class="size-4" /> Find people</a>
        </x-page-header>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl space-y-5">
        <x-people-search-form label="Find people" />

        <div data-region="friends" class="space-y-5">

        {{-- Add a friend: exact username or email only. No directory. --}}
        <form method="POST" action="{{ route('friends.store') }}" data-live class="rounded-2xl border border-line bg-white p-5">
            @csrf
            <label for="who" class="font-serif text-lg font-bold text-club tracking-tight">Add a friend</label>
            <p class="text-sm text-ink/70">Enter their exact username or email. Their username is on their locker, like @jordan.rivera.</p>
            <div class="mt-3 flex gap-2">
                <input id="who" name="who" value="{{ old('who') }}" required autocomplete="off" autocapitalize="none" placeholder="@username or email"
                    class="min-w-0 flex-1 rounded-xl border border-line bg-white px-4 py-2.5 outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                <button type="submit" class="rounded-xl bg-club px-5 py-2.5 font-semibold text-ivory hover:bg-club-dark">Send request</button>
            </div>
            @error('who')
                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </form>

        @if ($incoming->isNotEmpty())
            <section>
                <h2 class="eyebrow text-club">Waiting on you</h2>
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-gold/50 bg-white">
                    @foreach ($incoming as $request)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <x-avatar :user="$request->requester" class="size-10 text-xs" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $request->requester->name }}</span>
                                <span class="block truncate text-sm text-ink/70">{{ '@'.$request->requester->username }}</span>
                            </span>
                            <form method="POST" action="{{ route('friends.accept', $request) }}" data-live>
                                @csrf
                                <button type="submit" class="rounded-full bg-club px-4 py-1.5 text-sm font-semibold text-ivory hover:bg-club-dark">Accept</button>
                            </form>
                            <form method="POST" action="{{ route('friends.destroy', $request) }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full px-3 py-1.5 text-sm font-semibold text-ink/70 hover:text-ink">Decline</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section>
            <h2 class="eyebrow text-club">Your friends</h2>
            @if ($friends->isEmpty())
                <x-empty-state class="mt-3" message="Goals stick better with company. Add someone you trust to keep you honest." action="Find people" :href="route('people.search')" />
            @else
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($friends as $friend)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <a href="{{ $friend->profileUrl() }}" class="flex min-w-0 flex-1 items-center gap-3">
                                <x-avatar :user="$friend" class="size-10 text-xs" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-ink">{{ $friend->name }}</span>
                                    <span class="block truncate text-sm text-ink/70">{{ '@'.$friend->username }}</span>
                                </span>
                            </a>
                            <form method="POST" action="{{ route('friends.destroy', $friendships[$friend->id]) }}" data-live onsubmit="return confirm('Remove {{ addslashes($friend->name) }} from your friends?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-ink/70 hover:text-red-700">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($leagueMates->isNotEmpty())
            <section>
                <h2 class="eyebrow text-club">People in your leagues</h2>
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($leagueMates as $mate)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <a href="{{ $mate->profileUrl() }}" class="flex min-w-0 flex-1 items-center gap-3">
                                <x-avatar :user="$mate" class="size-10 text-xs" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-ink">{{ $mate->name }}</span>
                                    <span class="block truncate text-sm text-ink/70">{{ '@'.$mate->username }}</span>
                                </span>
                            </a>
                            <x-friend-button :user="$mate" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($outgoing->isNotEmpty())
            <section>
                <h2 class="eyebrow text-ink/70">Requests you sent</h2>
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($outgoing as $request)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-avatar :user="$request->addressee" class="size-9 text-xs" />
                            <span class="min-w-0 flex-1 truncate text-ink/75">{{ $request->addressee->name }} <span class="text-ink/70">&middot; waiting</span></span>
                            <form method="POST" action="{{ route('friends.destroy', $request) }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-ink/70 hover:text-ink">Cancel</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        </div>
    </div>
</x-layouts.app>

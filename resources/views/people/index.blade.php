<x-layouts.app title="Find people · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('highlights.index') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Highlights</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Find people</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Follow friends to see their highlights and cheer them on.</p>
        </div>
    </x-slot:hero>

    <form method="GET" action="{{ route('people.index') }}" class="mx-auto flex max-w-2xl gap-2">
        <label for="q" class="sr-only">Search by name or username</label>
        <input id="q" name="q" value="{{ $search }}" autofocus placeholder="Name or @username"
            class="min-w-0 flex-1 rounded-xl border border-line bg-white px-4 py-3 outline-none focus:border-club focus:ring-4 focus:ring-club/10">
        <button type="submit" class="rounded-xl bg-club px-5 py-3 font-semibold text-ivory hover:bg-club-dark">Search</button>
    </form>

    <div class="mx-auto mt-6 max-w-2xl">
        @if ($search === '')
            <p class="text-center text-ink/60">Search for a friend by name or username.</p>
        @elseif ($people->isEmpty())
            <p class="text-center text-ink/60">No one found for &ldquo;{{ $search }}&rdquo;. Only people whose lockers you're allowed to see show up.</p>
        @else
            <ul class="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($people as $person)
                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                        <a href="{{ $person->profileUrl() }}" class="flex min-w-0 items-center gap-3">
                            <x-avatar :user="$person" class="size-10 text-xs" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-ink">{{ $person->name }}</span>
                                <span class="block truncate text-sm text-ink/55">{{ '@'.$person->username }}</span>
                            </span>
                        </a>
                        <x-follow-button :user="$person" :following="$followingIds->contains($person->id)" />
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>

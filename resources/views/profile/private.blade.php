<x-layouts.app :title="$user->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto flex max-w-5xl items-center gap-5 px-4 pt-6 pb-12">
            <x-avatar :user="$user" tone="light" class="size-20 text-2xl ring-4 ring-ivory/25" />
            <div>
                <h1 class="font-serif text-3xl font-medium tracking-tight text-ivory">{{ $user->name }}</h1>
                <p class="mt-1 text-ivory/75">{{ '@'.$user->username }}</p>
            </div>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-xl rounded-2xl border border-line bg-white p-8 text-center">
        <p class="font-serif text-xl text-club">This profile is private</p>
        <p class="mt-2 text-ink/70">
            @if ($user->profile_visibility === App\ProfileVisibility::Leagues)
                {{ Str::before($user->name, ' ') }} shares their profile with league mates. Join a league together to see their goals and stats.
            @else
                {{ Str::before($user->name, ' ') }} keeps their profile to themselves.
            @endif
        </p>
    </div>
</x-layouts.app>

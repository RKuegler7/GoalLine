{{-- Someone's full friends list, with search. Friends are mutual, so there's no Followers or Following list. --}}
<x-layouts.app :title="'Friends · '.$user->name">
    <x-slot:hero>
        <x-page-header :title="$isOwn ? 'Your friends' : $user->name.'\'s friends'" :back="$user->profileUrl()" :back-label="$user->name" :subtitle="$friends->total().' '.Str::plural('friend', $friends->total())" />
    </x-slot:hero>

    <div class="mx-auto max-w-xl rounded-2xl border border-line bg-white p-3" data-first-content data-friends-list>
        <form method="GET" action="{{ route('profiles.friends', $user) }}" class="mb-2" role="search">
            <label for="friend-search" class="sr-only">Search friends</label>
            <input id="friend-search" name="q" value="{{ $search }}" type="search" placeholder="Search by name or username"
                class="w-full rounded-lg border border-line bg-ivory px-3 py-2 text-base outline-none focus:border-club">
        </form>
        @include('profile.partials.friends-list')
    </div>
</x-layouts.app>

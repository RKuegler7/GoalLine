{{-- A private account, Instagram style: the same header everyone in this GoalLine world sees (photo, name, @username,
     bio, counts and the friend button, so you know it's the right person and can add them), then a lock card in place
     of goals, posts, stats and history. --}}
<x-layouts.app :title="$user->name.' · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$user->name" />
    </x-slot:hero>

    @include('profile.partials.header', ['isOwn' => false, 'limited' => true])

    <section class="mt-3 rounded-2xl border border-line bg-white px-4 py-5 text-center" data-profile-private>
        <span class="mx-auto grid size-12 place-items-center rounded-full border-2 border-ink/80 text-ink" aria-hidden="true"><x-icon name="lock" class="size-6" /></span>
        <h2 class="mt-2 text-[17px] font-bold text-ink">This account is private</h2>
        <p class="mt-1 text-[14px] text-ink/75">
            @if ($user->profile_visibility === App\ProfileVisibility::Leagues)
                {{ Str::before($user->name, ' ') }} shares goals and stats with friends and league mates. Add them as a friend to see more.
            @else
                {{ Str::before($user->name, ' ') }} keeps goals, posts and stats to themselves.
            @endif
        </p>
    </section>
</x-layouts.app>
{{-- The profile header, Instagram style, on the green field mowed sideways (rows that line up with the photo and the
     counts): the photo on the left with three counts beside it, then the name (never cut off), @username and the whole
     bio, then one full-width button. Everyone in the same GoalLine world sees this much, even on a private account, so
     they know they've found the right person and can send a friend request. $limited (a private account) leaves out
     the location and "Why I'm here", and the counts aren't links (there are no sections to jump to).
     Expects: $user, $isOwn, $counts ([label, value, #section, key]), $mutualFriends, $limited. --}}
<section class="relative overflow-hidden rounded-2xl bg-club px-4 py-4 text-ivory shadow-sm" aria-label="{{ $user->name }}" data-profile-header>
    <div class="flex items-center gap-4">
        <x-avatar :user="$user" tone="light" class="size-24 shrink-0 text-[28px] ring-3 ring-gold" />
        <nav aria-label="{{ $user->name }} at a glance" class="grid min-w-0 flex-1 grid-cols-3 gap-1" data-profile-counts>
            @foreach ($counts as [$label, $value, $href, $key])
                @if ($limited)
                    <span data-count="{{ $key }}" class="flex min-h-14 flex-col items-center justify-center px-0.5 py-1 text-center">
                        <span class="text-[24px] leading-none font-extrabold text-gold-light tabular-nums">{{ $value }}</span>
                        <span class="mt-1 text-[12px] leading-tight font-semibold text-ivory">{{ $label }}</span>
                    </span>
                @else
                    {{-- In-page links: just a scroll, never a page visit (a visit would redraw the page and close what's open). --}}
                    <a href="{{ $href }}" data-turbo="false" @if ($key === 'friends') data-open-friends @endif data-count="{{ $key }}"
                        class="flex min-h-14 flex-col items-center justify-center rounded-xl px-0.5 py-1 text-center hover:bg-ivory/10">
                        <span class="text-[24px] leading-none font-extrabold text-gold-light tabular-nums">{{ $value }}</span>
                        <span class="mt-1 text-[12px] leading-tight font-semibold text-ivory">{{ $label }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </div>

    <div class="mt-3 min-w-0" data-profile-identity>
        <h2 class="flex items-center gap-1.5 text-[19px] leading-tight font-bold text-ivory [overflow-wrap:anywhere]" data-profile-name>
            {{ $user->name }}
            @if ($limited)
                <x-icon name="lock" class="size-4 shrink-0 text-gold-light" /><span class="sr-only">(private account)</span>
            @endif
        </h2>
        <p class="text-[13px] text-mint [overflow-wrap:anywhere]">{{ '@'.$user->username }}</p>
        @if ($user->bio)
            <p class="mt-1.5 text-[15px] leading-snug text-ivory [overflow-wrap:anywhere] whitespace-pre-line" data-profile-bio>{{ $user->bio }}</p>
        @elseif ($isOwn)
            <p class="mt-1.5 text-[14px] text-mint">Add a short bio so friends know what you're chasing.</p>
        @endif
        @if (! $limited && $user->location)
            <p class="mt-1 text-[13px] text-mint [overflow-wrap:anywhere]" data-profile-location>{{ $user->location }}</p>
        @endif
        @if (! $limited && $user->why_here)
            <p class="mt-1 text-[14px] text-ivory [overflow-wrap:anywhere]"><span class="font-semibold text-gold-light">Why I'm here:</span> {{ $user->why_here }}</p>
        @endif
    </div>

    <div class="mt-3" data-profile-actions>
        @if ($isOwn)
            <a href="{{ route('profile.edit') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-full bg-ivory px-4 text-[14px] font-semibold text-club hover:bg-white">Edit profile</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1 text-center md:hidden">
                @csrf
                <button type="submit" class="min-h-11 px-3 text-[13px] font-semibold text-mint hover:text-ivory">Log out</button>
            </form>
        @else
            <div @class(['items-center gap-2', 'grid grid-cols-[minmax(0,1fr)_auto]' => ! $limited, 'block' => $limited])>
                {{-- The relationship button fills the row (Accept and Decline split it). --}}
                <div class="min-w-0 [&_details]:w-full [&_form]:w-full [&_button]:w-full [&_summary]:w-full [&_summary]:justify-center">
                    <x-friend-button :user="$user" tone="light" />
                </div>
                @unless ($limited)
                    <x-nudge-button :user="$user" tone="light" />
                @endunless
            </div>
            @if (App\Models\UserMute::where('user_id', auth()->id())->where('muted_user_id', $user->id)->exists())
                <form method="POST" action="{{ route('users.mute', $user) }}" class="mt-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="min-h-11 w-full rounded-full border border-ivory/60 px-4 text-sm font-semibold text-ivory hover:bg-ivory/10" data-unmute>Muted &middot; Unmute</button>
                </form>
            @endif
            <p class="mt-1 text-[12px] text-mint" data-friend-note>Friends can see your League + friends posts.</p>
            @if ($mutualFriends > 0)
                <p class="text-[13px] text-mint" data-mutual-friends>{{ $mutualFriends }} mutual {{ Str::plural('friend', $mutualFriends) }}</p>
            @endif
        @endif
    </div>
</section>

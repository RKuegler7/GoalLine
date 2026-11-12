{{-- A page of someone's friends: avatar, name, username and a "Mutual" tag when you're friends too. Never an email.
     Loaded in place on the profile (the Friends row) and on the full list page; "Show more" loads the next page. --}}
<div data-friends-page>
    @if ($friends->isEmpty())
        <p class="px-1 py-2 text-[14px] text-ink/70">{{ $search !== '' ? 'No friends match "'.$search.'".' : ($isOwn ? 'No friends yet. Add people you know by username.' : 'No friends to show yet.') }}</p>
    @else
        <ul class="divide-y divide-line" data-friends-items>
            @foreach ($friends as $friend)
                <li>
                    <a href="{{ $friend->profileUrl() }}" class="flex min-h-12 items-center gap-2.5 px-1 py-1.5 hover:bg-mint/40" data-friend-row>
                        <x-avatar :user="$friend" class="size-9 text-[12px]" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[14px] font-semibold text-ink">{{ $friend->name }}</span>
                            @if ($friend->username)
                                <span class="block truncate text-[12px] text-ink/70">{{ '@'.$friend->username }}</span>
                            @endif
                        </span>
                        @if (! $isOwn && isset($mutual[$friend->id]))
                            <span class="eyebrow shrink-0 rounded-full bg-mint px-2 py-0.5 text-[10px] text-club">Mutual</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($friends->hasMorePages())
        <a href="{{ $friends->nextPageUrl() }}" class="mt-1 flex min-h-11 items-center justify-center rounded-full text-[14px] font-semibold text-club hover:bg-mint/50" data-friends-more>Show more</a>
    @endif
</div>

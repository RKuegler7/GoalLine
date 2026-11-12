{{-- Add friend, Request sent (tap to cancel), Accept / Decline, or Friends (with Remove), for someone else's locker,
     with one line on what friendship means. Friends are mutual: one list, no followers. --}}
@props(['user', 'tone' => 'dark', 'note' => false])

@php
    $me = auth()->user();
    $friendship = $me->friendshipWith($user);
    $light = $tone === 'light';
    $solid = $light ? 'bg-ivory text-club hover:bg-white' : 'bg-club text-ivory hover:bg-club-dark';
    $outline = $light ? 'border border-ivory/60 text-ivory hover:border-ivory hover:bg-ivory/10' : 'border border-club/30 text-club hover:border-club';
@endphp

@if (! $me->is($user))
    @if (! $friendship)
        <form method="POST" action="{{ route('friends.request', $user) }}" data-live>
            @csrf
            <button type="submit" class="min-h-11 rounded-full px-5 py-2 text-sm font-semibold transition {{ $solid }}">Add friend</button>
        </form>
    @elseif ($friendship->isAccepted())
        <details class="relative">
            <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-full px-5 py-2 text-sm font-semibold transition {{ $outline }} [&::-webkit-details-marker]:hidden">&check; Friends</summary>
            <form method="POST" action="{{ route('friends.destroy', $friendship) }}" data-live class="absolute right-0 z-20 mt-2 w-44 rounded-xl border border-line bg-white p-1 shadow-lg" onsubmit="return confirm('Remove {{ addslashes($user->name) }} from your friends?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50">Remove friend</button>
            </form>
        </details>
    @elseif ($friendship->requester_id === $me->id)
        <form method="POST" action="{{ route('friends.destroy', $friendship) }}" data-live>
            @csrf
            @method('DELETE')
            <button type="submit" class="min-h-11 rounded-full px-5 py-2 text-sm font-semibold transition {{ $outline }}" title="Cancel the request" aria-label="Request sent. Tap to cancel it.">Request sent &middot; Cancel</button>
        </form>
    @else
        <div class="flex gap-2">
            <form method="POST" action="{{ route('friends.accept', $friendship) }}" data-live>
                @csrf
                <button type="submit" class="min-h-11 rounded-full px-5 py-2 text-sm font-semibold transition {{ $solid }}">Accept</button>
            </form>
            <form method="POST" action="{{ route('friends.destroy', $friendship) }}" data-live>
                @csrf
                @method('DELETE')
                <button type="submit" class="min-h-11 rounded-full px-4 py-2 text-sm font-semibold transition {{ $outline }}">Decline</button>
            </form>
        </div>
    @endif
    @if ($note)
    <p @class(['mt-1 text-[11px]', 'text-ivory/80' => $light, 'text-ink/70' => ! $light]) data-friend-note>Friends can see your League + friends posts.</p>
    @endif
@endif

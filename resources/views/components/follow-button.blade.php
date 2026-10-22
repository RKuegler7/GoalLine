{{-- Follow or unfollow someone. --}}
@props(['user', 'following', 'tone' => 'dark'])

<form method="POST" action="{{ route($following ? 'follows.destroy' : 'follows.store', $user) }}" class="shrink-0">
    @csrf
    @if ($following)
        @method('DELETE')
    @endif
    <button type="submit" @class([
        'rounded-full px-4 py-1.5 text-sm font-semibold transition',
        'bg-club text-ivory hover:bg-club-dark' => ! $following && $tone === 'dark',
        'border border-line text-ink/70 hover:border-red-300 hover:text-red-700' => $following && $tone === 'dark',
        'bg-ivory text-club hover:bg-white' => ! $following && $tone === 'light',
        'border border-ivory/60 text-ivory hover:bg-ivory/10' => $following && $tone === 'light',
    ])>
        {{ $following ? 'Following' : 'Follow' }}
    </button>
</form>

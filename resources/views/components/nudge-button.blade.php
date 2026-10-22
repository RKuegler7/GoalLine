{{-- Nudge someone to check in, once a day. Renders nothing when the viewer can't nudge them. --}}
@props(['user', 'tone' => 'dark'])

@php
    $viewer = auth()->user();
@endphp

@if ($viewer->canNudge($user))
    @if ($viewer->recentlyNudged($user))
        <span {{ $attributes->class([
            'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold',
            'bg-ivory/15 text-ivory/70' => $tone === 'light',
            'bg-mint text-club/70' => $tone !== 'light',
        ]) }}>Nudged today</span>
    @else
        <form method="POST" action="{{ route('nudges.store', $user) }}" {{ $attributes }}>
            @csrf
            <button type="submit" @class([
                'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                'border border-ivory/60 text-ivory hover:border-ivory hover:bg-ivory hover:text-club' => $tone === 'light',
                'border border-club/30 text-club hover:border-club hover:bg-mint' => $tone !== 'light',
            ])>
                <x-icon name="bell" class="size-4" /> Nudge {{ Str::before($user->name, ' ') }}
            </button>
        </form>
    @endif
@endif

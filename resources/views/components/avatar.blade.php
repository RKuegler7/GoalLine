{{-- A member's profile photo, or their initials. Use tone="light" on Club Green backgrounds. --}}
@props(['user', 'tone' => 'dark'])

@php
    // Always a set size: a photo with none would show at its full size.
    $sized = str_contains((string) $attributes->get('class'), 'size-');
@endphp

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" loading="lazy" decoding="async" {{ $attributes->class(['shrink-0 rounded-full object-cover', 'size-9' => ! $sized]) }}>
@else
    <span {{ $attributes->class([
        'flex shrink-0 items-center justify-center rounded-full font-semibold',
        'size-9 text-xs' => ! $sized,
        'bg-club text-ivory' => $tone === 'dark',
        'bg-ivory text-club' => $tone === 'light',
    ]) }} aria-hidden="true">
        {{ $user->initials() }}
    </span>
@endif

{{-- A member's profile photo, or their initials. Use tone="light" on Club Green backgrounds. --}}
@props(['user', 'tone' => 'dark'])

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" {{ $attributes->class('shrink-0 rounded-full object-cover') }}>
@else
    <span {{ $attributes->class([
        'flex shrink-0 items-center justify-center rounded-full font-semibold',
        'bg-club text-ivory' => $tone === 'dark',
        'bg-ivory text-club' => $tone === 'light',
    ]) }} aria-hidden="true">
        {{ $user->initials() }}
    </span>
@endif

{{-- The "GoalLine" wordmark on its own. Tone "light" sits on Club Green, "dark" on white or ivory. --}}
@props(['tone' => 'light'])

<svg
    {{ $attributes->class(['h-6 w-auto', $tone === 'light' ? 'text-ivory' : 'text-club']) }}
    viewBox="3 22 403.5 81.5"
    role="img"
    aria-label="GoalLine"
>
    <x-brand.letters />
</svg>

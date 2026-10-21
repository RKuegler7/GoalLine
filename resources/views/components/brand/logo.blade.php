{{--
    Primary lockup: the wordmark followed by the signature Line (stroke, marker, end post),
    set at the middle of the lowercase letters. Optionally adds the tagline beneath.
    The marker position is a fixed brand element. For a user's real progress, use <x-goal-progress>.
--}}
@props(['tone' => 'light', 'tagline' => false])

@php
    $light = $tone === 'light';
    $rest = $light ? '#e5f3e8' : '#43b96a';
    $post = $light ? '#e5f3e8' : '#176b45';
    $marker = $light ? '#43b96a' : '#176b45';
@endphp

<span @class(['inline-flex flex-col items-center gap-3', $light ? 'text-ivory' : 'text-club'])>
    <svg {{ $attributes->class('h-9 w-auto') }} viewBox="3 22 518 81.5" role="img" aria-label="GoalLine">
        <x-brand.letters />

        <g stroke-width="6" stroke-linecap="round">
            <path d="M420 73 H468" stroke="#43b96a" />
            <path d="M468 73 H514" stroke="{{ $rest }}" stroke-opacity="{{ $light ? 0.6 : 0.45 }}" />
            <path d="M514 49 V97" stroke="{{ $post }}" />
        </g>
        <circle cx="468" cy="73" r="10" fill="{{ $marker }}" />
    </svg>

    @if ($tagline)
        <span class="eyebrow tracking-[0.35em] whitespace-nowrap">Get in line with your goals.</span>
    @endif
</span>

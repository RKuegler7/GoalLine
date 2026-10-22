{{-- A badge medallion: green for personal badges, gold for GoalLeague awards, an outline until it's earned. --}}
@props(['badge', 'earned' => true])

@php
    $award = $badge->isLeagueAward();
    $fill = ! $earned ? 'none' : ($award ? '#d4ac58' : '#176b45');
    $ring = ! $earned ? '#d9ddd5' : ($award ? '#b58f3e' : '#43b96a');
    $ink = ! $earned ? '#b7beb5' : ($award ? '#1e2922' : '#fafaf5');
@endphp

<svg {{ $attributes->class('shrink-0') }} viewBox="0 0 64 64" role="img" aria-label="{{ $badge->label() }}{{ $earned ? '' : ' (not earned yet)' }}">
    <circle cx="32" cy="32" r="29" fill="{{ $fill }}" stroke="{{ $ring }}" stroke-width="4" @if (! $earned) stroke-dasharray="5 4" @endif />
    <g fill="none" stroke="{{ $ink }}" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
        @switch($badge->emblem())
            @case('step')
                <path d="M20 42h8v-8h8v-8h8" />
                @break
            @case('line')
                <path d="M18 38h28" stroke-width="4" />
                <circle cx="38" cy="38" r="4.5" fill="{{ $ink }}" />
                <path d="M24 30l4 4 9-10" />
                @break
            @case('bolt')
                <path d="M35 16l-12 18h10l-4 14 12-18H31z" fill="{{ $ink }}" stroke-width="2" />
                @break
            @case('flame')
                <path d="M32 47c-7 0-11-5-11-10 0-7 7-10 6-18 5 3 8 7 8 12 2-1 3-3 3-5 4 3 5 7 5 11 0 6-4 10-11 10z" />
                @break
            @case('repeat')
                <path d="M41 20l4 4-4 4" />
                <path d="M20 31v-1a6 6 0 0 1 6-6h19" />
                <path d="M23 44l-4-4 4-4" />
                <path d="M44 33v1a6 6 0 0 1-6 6H19" />
                @break
            @case('flag')
                <path d="M23 48V16" />
                <path d="M23 18h20l-4 7 4 7H23" fill="{{ $ink }}" fill-opacity="0.25" />
                @break
            @case('people')
                <circle cx="25" cy="26" r="5" />
                <circle cx="40" cy="26" r="5" />
                <path d="M16 44c1-6 5-9 9-9s8 3 9 9M31 44c1-6 5-9 9-9s8 3 9 9" />
                @break
            @case('clap')
                <path d="M22 34l8-12a3 3 0 0 1 5 3l-4 7 9-9a3 3 0 0 1 4 4l-9 10 7-5a3 3 0 0 1 3 4l-9 8c-6 5-14 3-16-3-1-3 0-5 2-7z" />
                @break
            @case('trophy')
                <path d="M24 17h16v9a8 8 0 0 1-16 0z" fill="{{ $ink }}" fill-opacity="0.2" />
                <path d="M24 20h-4a4 4 0 0 0 4 7M40 20h4a4 4 0 0 1-4 7" />
                <path d="M32 34v6M26 47h12M28 40h8v7h-8z" />
                @break
            @case('star')
                <path d="M32 15l5 10.5 11.5 1.5-8.5 8 2 11.5L32 41l-10 5.5 2-11.5-8.5-8L27 25.5z" fill="{{ $ink }}" fill-opacity="0.2" />
                @break
            @case('rise')
                <path d="M17 45l10-10 7 6 13-15" />
                <path d="M38 26h9v9" />
                @break
        @endswitch
    </g>
</svg>

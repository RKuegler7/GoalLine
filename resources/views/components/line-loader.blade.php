{{-- GoalLine's loader: the line draws left to right with the dot running along it. When loading finishes (is-done), the
     dot runs past the end tick and smashes the line with a small blue diamond. One component, three sizes:
     - splash: full screen, the wordmark over the line (first visit of a session only; see x-splash),
     - inline: a section loader, shown only if loading takes more than a moment (delayed),
     - button: just the line and dot, for a button at work.
     With reduced motion it's a still line and a simple fade (app.css). --}}
@props(['size' => 'inline', 'label' => 'Loading', 'delayed' => true])

<div {{ $attributes->class([
    'gl-loader',
    'gl-loader--'.$size,
    'gl-loader--delayed' => $delayed && $size === 'inline',
]) }} role="status" aria-live="polite">
    @if ($size === 'splash')
        <x-brand.wordmark class="gl-loader__wordmark h-10 w-auto sm:h-12" />
    @endif
    <svg viewBox="0 0 300 24" class="gl-loader__line" aria-hidden="true">
        <line x1="6" y1="12" x2="278" y2="12" class="gl-loader__track" stroke-width="4" stroke-linecap="round" />
        <line x1="6" y1="12" x2="278" y2="12" class="gl-loader__draw" stroke-width="4" stroke-linecap="round" pathLength="1" />
        <line x1="286" y1="3" x2="286" y2="21" class="gl-loader__tick" stroke-width="3" stroke-linecap="round" />
        <circle cx="6" cy="12" r="6" class="gl-loader__dot" />
        <path d="M286 2l10 10-10 10-10-10z" class="gl-loader__diamond" />
    </svg>
    <span @class(['sr-only' => $size !== 'inline', 'gl-loader__label' => $size === 'inline'])>{{ $label }}</span>
</div>

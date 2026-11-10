{{-- The band-up moment, shared by every page: a short card (under 1.5 seconds, tap to skip) when a check-in moves a
     GoalWeek up a band. The line fills and the dot pulses for In line, a golden shine for Past the line, and a blue
     diamond burst for Smashed, with "Add proof to lock it in" when proof is still needed. Played by celebrate.js; a
     screen reader hears the new band. Reduced motion gets a simple fade. Going down a band never shows anything. --}}
<div data-band-celebration hidden role="status" aria-live="polite"
    class="gl-celebrate pointer-events-auto fixed inset-x-0 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-50 flex justify-center px-4 md:bottom-10">
    <div data-celebration-card class="gl-celebrate__card w-full max-w-sm cursor-pointer rounded-2xl bg-club px-5 py-4 text-left text-ivory shadow-2xl" title="Tap to close">
        <svg viewBox="0 0 300 24" class="h-6 w-full" aria-hidden="true">
            <line x1="6" y1="12" x2="278" y2="12" stroke="currentColor" stroke-opacity="0.25" stroke-width="4" stroke-linecap="round" />
            <line data-celebration-line x1="6" y1="12" x2="278" y2="12" stroke="#43b96a" stroke-width="4" stroke-linecap="round" pathLength="1" />
            <line x1="286" y1="3" x2="286" y2="21" stroke="currentColor" stroke-opacity="0.6" stroke-width="3" stroke-linecap="round" />
            <circle data-celebration-dot cx="278" cy="12" r="6" fill="#43b96a" />
            <path data-celebration-diamond d="M286 2l10 10-10 10-10-10z" fill="#0369a1" />
        </svg>
        <p data-celebration-title class="mt-2 text-[20px] leading-tight font-bold"></p>
        <p data-celebration-detail class="mt-0.5 text-[13px] text-ivory/90"></p>
        <a data-celebration-proof hidden href="#" class="mt-2 inline-flex min-h-10 items-center rounded-full bg-ivory px-4 text-[13px] font-semibold text-club">Add proof to lock it in</a>
    </div>
</div>

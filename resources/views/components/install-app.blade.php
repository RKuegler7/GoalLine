{{-- "Get the app": shown by app.js only when the browser can install GoalLine and it isn't installed yet. --}}
<section data-install-card hidden {{ $attributes->class('relative overflow-hidden rounded-2xl bg-club px-5 py-4 text-ivory') }}>
    <x-field-backdrop class="absolute inset-0 size-full opacity-60" />
    <div class="relative flex items-start gap-4">
        <img src="/icons/app-icon.svg" alt="" class="size-12 shrink-0 rounded-xl ring-2 ring-ivory/30">
        <div class="min-w-0 flex-1">
            <h2 class="font-serif text-lg font-medium">Put GoalLine on your home screen</h2>
            <p data-install-variant="prompt" class="mt-1 text-sm text-ivory/85">One tap to today&rsquo;s gameplan, full screen, like any app.</p>
            <p data-install-variant="ios" hidden class="mt-1 text-sm text-ivory/85">
                In Safari, tap <span class="font-semibold text-ivory">Share</span>
                <svg viewBox="0 0 24 24" class="inline size-4 -translate-y-px" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4M6 11H5v9h14v-9h-1" /></svg>
                then <span class="font-semibold text-ivory">Add to Home Screen</span>.
            </p>
            <div class="mt-3 flex items-center gap-3">
                <button type="button" data-install-button data-install-variant="prompt" class="rounded-full bg-ivory px-4 py-1.5 text-sm font-semibold text-club transition hover:bg-white">Install GoalLine</button>
                <button type="button" data-install-dismiss class="text-sm font-medium text-ivory/70 hover:text-ivory">Not now</button>
            </div>
        </div>
    </div>
</section>

{{-- "Turn on notifications": shown by app.js only where push works and the member hasn't decided yet. --}}
<section data-push-nudge hidden {{ $attributes->class('flex items-center gap-4 rounded-2xl border border-club/25 bg-mint/60 px-5 py-4') }}>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-club text-ivory"><x-icon name="bell" class="size-5" /></span>
    <p class="min-w-0 flex-1 text-sm text-ink/80"><span class="font-semibold text-ink">Never miss a GoalWeek.</span> Get your morning gameplan and an evening nudge on this device.</p>
    <a href="{{ route('settings.notifications') }}" class="shrink-0 rounded-full bg-club px-4 py-1.5 text-sm font-semibold text-ivory hover:bg-club-dark">Turn on</a>
    <button type="button" data-push-nudge-dismiss class="shrink-0 text-sm text-ink/50 hover:text-ink" aria-label="Not now">&times;</button>
</section>

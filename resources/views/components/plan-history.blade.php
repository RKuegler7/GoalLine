{{-- Plan history: every saved version of the roadmap, newest first. An earlier one can be brought back for the
     GoalWeeks that haven't started, after a preview. --}}
@props(['goal'])

@php
    $versions = $goal->planVersions()->latest('id')->limit(20)->get();
@endphp

@if ($versions->isNotEmpty())
    <details id="plan-history" class="mt-6 scroll-mt-6 rounded-2xl border border-line bg-white px-6 py-4">
        <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <span class="eyebrow text-club">Plan history</span>
            <span class="ml-2 text-sm text-ink/70">{{ $versions->count() }} {{ Str::plural('version', $versions->count()) }}</span>
        </summary>
        <ol class="mt-3 divide-y divide-line">
            @foreach ($versions as $version)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">
                            {{ $version->label() }}
                            <span class="font-normal text-ink/70">&middot; {{ App\Clock::local($version->created_at)->format('M j, g:i a') }}</span>
                            @if ($loop->first)
                                <span class="ml-1 rounded-full bg-mint px-2 py-0.5 text-[0.65rem] font-semibold text-club">Current</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm text-ink/65">{{ $version->summary }}</p>
                    </div>
                    @if (! $loop->first && $goal->isActive())
                        <form method="POST" action="{{ route('goals.plan-versions.restore', [$goal, $version]) }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-line px-3 py-1 text-xs font-semibold text-club hover:border-club/40">Preview restoring</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ol>
        <p class="mt-1 text-xs text-ink/70">Restoring only changes GoalWeeks that haven't started. Every change is saved here.</p>
    </details>
@endif

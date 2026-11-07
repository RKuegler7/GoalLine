<x-layouts.app :title="'Preview your plan change · GoalLine'">
    @if ($refinement->status === App\Models\PlanRefinement::PENDING)
        @push('head')
            <meta http-equiv="refresh" content="4">
        @endpush
    @endif

    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('goals.show', $goal) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $goal->title }}</a>
            <p class="eyebrow mt-6 text-mint">{{ $refinement->kind === App\Models\PlanRefinement::RESTORE ? 'Plan history' : 'Refine your plan' }}</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">
                {{ $refinement->status === App\Models\PlanRefinement::PENDING ? 'Working on it…' : 'Preview the change' }}
            </h1>
            @if ($refinement->instruction)
                <p class="mt-3 max-w-xl text-ivory/85">&ldquo;{{ $refinement->instruction }}&rdquo;</p>
            @endif
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-3xl">
        @if ($refinement->status === App\Models\PlanRefinement::PENDING)
            <section class="rounded-2xl border border-line bg-white px-8 py-10 text-center">
                <h2 class="font-serif text-2xl font-bold text-club tracking-tight">Your coach is reworking the plan&hellip;</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">This usually takes under a minute. Nothing changes until you've seen it and said yes.</p>
                <div class="mx-auto mt-6 h-1.5 w-40 overflow-hidden rounded-full bg-mint" aria-hidden="true">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-grass"></div>
                </div>
                <form method="POST" action="{{ route('goals.refinements.discard', [$goal, $refinement]) }}" data-live class="mt-6">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-ink/60 hover:text-ink">Cancel</button>
                </form>
            </section>
        @elseif ($refinement->status === App\Models\PlanRefinement::FAILED)
            <section class="rounded-2xl border border-line bg-white px-8 py-10 text-center">
                <h2 class="font-serif text-2xl font-bold text-club tracking-tight">That change didn't come through</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">Nothing in your plan changed. Try again, maybe with a little more detail, or edit the tasks yourself.</p>
                <a href="{{ route('goals.show', $goal) }}#refine" class="mt-6 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Back to your plan</a>
            </section>
        @else
            <p class="rounded-2xl bg-mint px-5 py-4 text-ink"><span class="font-semibold text-club">Why:</span> {{ $refinement->explanation }}</p>

            @if ($diff['weeks'] === [])
                <p class="mt-4 rounded-2xl border border-line bg-white px-5 py-4 text-ink/70">This wouldn't change anything in the GoalWeeks that can still change.</p>
            @endif

            <ol class="mt-4 space-y-3">
                @foreach ($diff['weeks'] as $change)
                    @php
                        $week = $change['week'];
                        $delta = $change['pointsAfter'] - $change['pointsBefore'];
                    @endphp
                    <li class="rounded-2xl border border-line bg-white px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-club">{{ $week->label() }} <span class="font-normal text-ink/50">&middot; {{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format('M j') }}</span></p>
                            <p class="text-sm text-ink/60 tabular-nums">
                                Planned points {{ $change['pointsBefore'] }} &rarr; <span class="font-semibold text-ink">{{ $change['pointsAfter'] }}</span>
                                @if ($delta !== 0)
                                    <span @class(['ml-1', 'text-club' => $delta > 0, 'text-ink/60' => $delta < 0])>({{ $delta > 0 ? '+' : '' }}{{ $delta }})</span>
                                @endif
                            </p>
                        </div>
                        @if ($change['raised'])
                            <p class="mt-1 text-xs text-club">That would be below the minimum pace your deadline needs, so GoalLine raises it to {{ $change['floor'] }} points with minimum-pace sessions.</p>
                        @endif
                        @if ($change['focus'])
                            <p class="mt-2 text-sm"><span class="text-ink/55">Focus:</span> <span class="text-ink/50 line-through">{{ $change['focus'][0] }}</span> &rarr; <span class="font-medium text-ink">{{ $change['focus'][1] }}</span></p>
                        @endif
                        @if ($change['milestone'])
                            <p class="mt-1 text-sm"><span class="text-ink/55">Milestone:</span> <span class="text-ink/50 line-through">{{ $change['milestone'][0] }}</span> &rarr; <span class="font-medium text-ink">{{ $change['milestone'][1] }}</span></p>
                        @endif
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($change['added'] as $task)
                                <li class="flex gap-2"><span class="font-bold text-grass">+</span> <span class="text-ink">{{ $task }}</span> <span class="text-ink/45">added</span></li>
                            @endforeach
                            @foreach ($change['moved'] as $move)
                                <li class="flex gap-2"><span class="font-bold text-club">&rarr;</span> <span class="text-ink">{{ $move['description'] }}</span> <span class="text-ink/45">moved from {{ $move['from'] }}</span></li>
                            @endforeach
                            @foreach ($change['removed'] as $task)
                                <li class="flex gap-2"><span class="font-bold text-ink/45">&minus;</span> <span class="text-ink/50 line-through">{{ $task }}</span> <span class="text-ink/45">removed</span></li>
                            @endforeach
                            @if ($change['reordered'])
                                <li class="text-ink/55">Tasks reordered.</li>
                            @endif
                        </ul>
                    </li>
                @endforeach
            </ol>

            @if ($diff['skipped'])
                <p class="mt-3 text-sm text-ink/60">{{ count($diff['skipped']) }} {{ Str::plural('GoalWeek', count($diff['skipped'])) }} in this change started since it was made, so {{ count($diff['skipped']) === 1 ? 'it stays' : 'they stay' }} as is.</p>
            @endif

            <p class="mt-4 text-xs text-ink/55">Only GoalWeeks that haven't started change, and none goes below the minimum pace its deadline needs. Past check-ins, group tasks, and your personal tasks stay as they are. New tasks are worth 1 point each.</p>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <form method="POST" action="{{ route('goals.refinements.discard', [$goal, $refinement]) }}" data-live>
                    @csrf
                    <button type="submit" class="w-full rounded-full px-6 py-2.5 font-semibold text-ink/70 hover:text-ink sm:w-auto">Discard</button>
                </form>
                @if ($diff['weeks'] !== [])
                    <form method="POST" action="{{ route('goals.refinements.accept', [$goal, $refinement]) }}" data-live>
                        @csrf
                        <button type="submit" class="w-full rounded-full bg-club px-8 py-2.5 font-semibold text-ivory hover:bg-club-dark sm:w-auto">Accept and update my plan</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>

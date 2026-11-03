<x-layouts.app :title="$member->user->name.'\'s plan · '.$league->name">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.show', $league) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $league->name }}</a>
            <p class="eyebrow mt-6 text-mint">Plan transparency</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">{{ $member->user->name }}&rsquo;s plan</h1>
            <p class="mt-3 max-w-xl text-ivory/85">{{ $goal?->title ?? 'No goal in this league right now.' }}</p>
        </div>
    </x-slot:hero>

    @if ($goal)
        <p class="rounded-2xl bg-mint px-5 py-4 text-sm text-ink/80">
            Every GoalWeek has a minimum pace its deadline needs, worked out by GoalLine&rsquo;s code (never the AI). A plan can be harder than its minimum, never easier, and it locks when the week starts. Two lighter weeks a season are allowed, at 70% of the minimum, with a reason the league can see.
        </p>

        <div class="mt-4 overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[30rem] text-left text-sm">
                <thead class="border-b border-line text-xs text-ink/55 uppercase">
                    <tr>
                        <th class="px-4 py-3 font-semibold">GoalWeek</th>
                        <th class="px-2 py-3 text-right font-semibold">Planned</th>
                        <th class="px-2 py-3 text-right font-semibold">Minimum</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($goal->weeks as $week)
                        @php
                            $status = $week->planStatus();
                        @endphp
                        <tr @class(['bg-mint/30' => $week->isCurrent()])>
                            <td class="px-4 py-2.5">
                                <span class="font-medium text-ink">{{ $week->label() }}</span>
                                <span class="block text-xs text-ink/50">{{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format('M j') }}{{ $week->isCurrent() ? ' · this week' : '' }}</span>
                            </td>
                            <td class="px-2 py-2.5 text-right tabular-nums">{{ $week->plannedPoints() }}</td>
                            <td class="px-2 py-2.5 text-right tabular-nums text-ink/60">{{ $week->effectiveFloor() ?? '–' }}</td>
                            <td class="px-4 py-2.5">
                                <x-plan-status :week="$week" />
                                @if ($week->isLighter())
                                    <span class="mt-0.5 block text-xs text-ink/55">&ldquo;{{ $week->lighter_reason }}&rdquo;</span>
                                @endif
                                @if ($week->floor_note)
                                    <span class="mt-0.5 block text-xs text-ink/55">Raised to the minimum pace.</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($versions->isNotEmpty())
            <section class="mt-6 rounded-2xl border border-line bg-white px-6 py-5">
                <h2 class="eyebrow text-club">Plan history</h2>
                <ol class="mt-2 divide-y divide-line">
                    @foreach ($versions as $version)
                        <li class="py-2.5 text-sm">
                            <span class="font-semibold text-ink">{{ $version->label() }}</span>
                            <span class="text-ink/50">&middot; {{ App\Clock::local($version->created_at)->format('M j, g:i a') }}</span>
                            <p class="text-ink/65">{{ $version->summary }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @endif
</x-layouts.app>

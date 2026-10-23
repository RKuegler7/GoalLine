@php
    $peak = max(1, collect($dailyActive)->max('count'));
    $money = fn (float $amount) => '$'.number_format($amount, 2);
@endphp

<x-layouts.app title="Admin · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <p class="eyebrow text-mint">{{ $inviteOnly ? 'Private beta' : 'Open signup' }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Admin</h1>
            <dl class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([
                    ['Members', $members['total'], '+'.$members['lastWeek'].' this week'],
                    ['Active today', $active['today'], $active['week'].' this week · '.$active['month'].' this month'],
                    ['Regulars', $active['regulars'], 'active 3+ of the last 7 days'],
                    ['AI today', $money($ai['today']), 'of '.$money($ai['dailyBudget']).' daily budget'],
                ] as [$label, $value, $note])
                    <div class="rounded-xl bg-ivory/10 px-4 py-3 ring-1 ring-ivory/15 ring-inset">
                        <dt class="eyebrow text-[0.62rem] text-mint">{{ $label }}</dt>
                        <dd class="mt-1 font-serif text-3xl font-medium text-ivory tabular-nums">{{ $value }}</dd>
                        <dd class="text-xs text-ivory/65">{{ $note }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-slot:hero>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Daily active members. --}}
        <section class="rounded-2xl border border-line bg-white px-5 py-4">
            <h2 class="eyebrow text-club">Active members, last 14 days</h2>
            <ol class="mt-4 flex h-32 items-end gap-1.5" aria-label="Active members per day">
                @foreach ($dailyActive as $day)
                    <li class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $day['date']->format('D M j') }}: {{ $day['count'] }}">
                        <span class="text-[0.6rem] text-ink/50 tabular-nums">{{ $day['count'] ?: '' }}</span>
                        <span class="w-full rounded-t bg-grass" style="height: {{ max(2, round($day['count'] / $peak * 100)) }}%"></span>
                        <span class="text-[0.6rem] text-ink/40">{{ $day['date']->format('j') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- AI spend. --}}
        <section class="rounded-2xl border border-line bg-white px-5 py-4">
            <h2 class="eyebrow text-club">AI spend</h2>
            @foreach ([['Today', $ai['today'], $ai['dailyBudget']], ['This month', $ai['month'], $ai['monthlyBudget']]] as [$label, $spent, $limit])
                <div class="mt-3">
                    <div class="flex justify-between text-sm"><span class="text-ink/70">{{ $label }}</span><span class="font-semibold text-ink tabular-nums">{{ $money($spent) }} / {{ $money($limit) }}</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-mint">
                        <div @class(['h-full rounded-full', 'bg-grass' => $spent < $limit * 0.8, 'bg-amber-500' => $spent >= $limit * 0.8 && $spent < $limit, 'bg-red-500' => $spent >= $limit]) style="width: {{ min(100, $limit > 0 ? round($spent / $limit * 100) : 100) }}%"></div>
                    </div>
                </div>
            @endforeach
            <p class="mt-3 text-xs text-ink/50">Estimates from token counts. AI pauses for everyone at either limit; each member is also capped at {{ config('goalline.ai.daily_requests_per_member') }} AI requests a day.</p>
            @if ($ai['byKind'])
                <table class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @foreach ($ai['byKind'] as $row)
                            <tr><td class="py-1.5 text-ink/75">{{ Str::headline($row['kind']) }}</td><td class="py-1.5 text-right text-ink/60 tabular-nums">{{ $row['requests'] }} calls</td><td class="py-1.5 text-right font-semibold text-ink tabular-nums">{{ $money($row['cost']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if ($ai['topMembersToday'])
                <p class="mt-3 text-xs text-ink/50">Most AI today: {{ collect($ai['topMembersToday'])->map(fn ($row) => $row['name'].' ('.$row['requests'].')')->join(', ') }}</p>
            @endif
        </section>

        {{-- Feature use. --}}
        <section class="rounded-2xl border border-line bg-white px-5 py-4 lg:col-span-2">
            <h2 class="eyebrow text-club">What people used, last 7 days</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs text-ink/50">
                    <tr><th class="py-1.5 font-semibold">Feature</th><th class="py-1.5 text-right font-semibold">Uses</th><th class="py-1.5 text-right font-semibold">Members</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($features as $feature)
                        <tr @class(['text-ink/40' => $feature['uses'] === 0])>
                            <td class="py-1.5">{{ $feature['label'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $feature['uses'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $feature['members'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        {{-- Beta feedback. --}}
        <section id="feedback" class="scroll-mt-6 rounded-2xl border border-line bg-white px-5 py-4 lg:col-span-2">
            <div class="flex items-end justify-between gap-4">
                <h2 class="eyebrow text-club">Beta feedback</h2>
                <span class="text-sm text-ink/50">{{ $feedback->whereNull('resolved_at')->count() }} open</span>
            </div>
            @if ($feedback->isEmpty())
                <p class="mt-3 text-sm text-ink/60">No notes yet. Testers send them with the Feedback button at the bottom right of every page.</p>
            @else
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($feedback as $note)
                        <li @class(['flex flex-wrap items-start gap-3 py-3', 'opacity-50' => $note->resolved_at])>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-ink/50">{{ $note->member ?? 'Former member' }} &middot; {{ App\Clock::shortTime(Carbon\Carbon::parse($note->created_at)) }}@if ($note->page) &middot; on <code>{{ $note->page }}</code>@endif</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $note->body }}</p>
                            </div>
                            @unless ($note->resolved_at)
                                <form method="POST" action="{{ route('admin.feedback.resolve', $note->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-full border border-club/30 px-3 py-1 text-xs font-semibold text-club hover:border-club">Mark handled</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Beta invites. --}}
        <section id="invites" class="scroll-mt-6 rounded-2xl border border-line bg-white px-5 py-4 lg:col-span-2">
            <h2 class="eyebrow text-club">Beta invites</h2>
            <form method="POST" action="{{ route('admin.invites.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-48 flex-1">
                    <label for="note" class="text-sm font-medium text-ink/80">For</label>
                    <input id="note" name="note" maxlength="120" placeholder="Sam, from the run club" class="mt-1 block w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                </div>
                <div>
                    <label for="max_uses" class="text-sm font-medium text-ink/80">People</label>
                    <input id="max_uses" name="max_uses" type="number" min="1" max="50" value="1" class="mt-1 block w-24 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                </div>
                <button type="submit" class="rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Make invite</button>
            </form>

            @if ($invites->isNotEmpty())
                <ul class="mt-4 divide-y divide-line">
                    @foreach ($invites as $invite)
                        <li class="flex flex-wrap items-center gap-3 py-2.5 text-sm">
                            <span class="font-mono font-semibold tracking-wider text-ink">{{ $invite->code }}</span>
                            <span class="min-w-0 flex-1 truncate text-ink/60">{{ $invite->note ?: 'No note' }}</span>
                            <span class="text-ink/60 tabular-nums">{{ $invite->uses }}/{{ $invite->max_uses }} used</span>
                            @if ($invite->isUsable())
                                <input type="text" readonly value="{{ $invite->signupUrl() }}" id="invite-{{ $invite->id }}" onclick="this.select()" class="sr-only">
                                <button type="button" data-copy="invite-{{ $invite->id }}" class="rounded-full border border-club/30 px-3 py-1 text-xs font-semibold text-club hover:border-club">Copy link</button>
                                <form method="POST" action="{{ route('admin.invites.revoke', $invite) }}" onsubmit="return confirm('Revoke this invite? Nobody new can join with it.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-ink/50 hover:text-red-700">Revoke</button>
                                </form>
                            @else
                                <span class="eyebrow rounded-full bg-ivory px-2 py-0.5 text-[0.6rem] text-ink/50">{{ $invite->revoked_at ? 'Revoked' : 'Used up' }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>

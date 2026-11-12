@php
    $peak = max(1, collect($dailyActive)->max('count'));
    $money = fn (float $amount) => '$'.number_format($amount, 2);
@endphp

<x-layouts.app title="Admin · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <p class="eyebrow text-mint">{{ $inviteOnly ? 'Private beta' : 'Open signup' }}</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">Admin</h1>
            <dl class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([
                    ['Members', $members['total'], '+'.$members['lastWeek'].' this week'],
                    ['Active today', $active['today'], $active['week'].' this week · '.$active['month'].' this month'],
                    ['Regulars', $active['regulars'], 'active 3+ of the last 7 days'],
                    ['AI today', $money($ai['today']), 'of '.$money($ai['dailyBudget']).' daily budget'],
                ] as [$label, $value, $note])
                    <div class="rounded-xl bg-ivory/10 px-4 py-3 ring-1 ring-ivory/15 ring-inset">
                        <dt class="eyebrow text-[11px] text-mint">{{ $label }}</dt>
                        <dd class="mt-1 font-serif text-3xl font-bold text-ivory tabular-nums tracking-tight">{{ $value }}</dd>
                        <dd class="text-xs text-ivory/85">{{ $note }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-slot:hero>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-2">
        {{-- Daily active members. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3">
            <h2 class="eyebrow text-club">Active members, last 14 days</h2>
            <ol class="mt-4 flex h-32 items-end gap-1.5" aria-label="Active members per day">
                @foreach ($dailyActive as $day)
                    <li class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $day['date']->format('D M j') }}: {{ $day['count'] }}">
                        <span class="text-[11px] text-ink/70 tabular-nums">{{ $day['count'] ?: '' }}</span>
                        <span class="w-full rounded-t bg-grass" style="height: {{ max(2, round($day['count'] / $peak * 100)) }}%"></span>
                        <span class="text-[11px] text-ink/70">{{ $day['date']->format('j') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Goal Day and medals: counts only, never names or notes. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3" data-admin-goal-day>
            <h2 class="eyebrow text-club">Goal Day and medals</h2>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                @foreach (['Answered' => $goalDay['answered'], 'Achieved' => $goalDay['achieved'], 'Partly' => $goalDay['partly'], 'Short' => $goalDay['short'], 'Verified' => $goalDay['verified'], 'Shared' => $goalDay['shared']] as $label => $value)
                    <div><dt class="text-ink/70">{{ $label }}</dt><dd class="text-lg font-bold text-ink tabular-nums">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-3 text-sm text-ink/75">
                @forelse ($goalDay['medals'] as $medal => $count)
                    {{ $medal }}: <span class="font-semibold tabular-nums">{{ $count }}</span>@if (! $loop->last) &middot; @endif
                @empty
                    No medals awarded yet.
                @endforelse
            </p>
        </section>

        {{-- Photo storage: S3 keeps uploads through deploys. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3 lg:col-span-2">
            <h2 class="eyebrow text-club">Photo storage</h2>
            @if ($storage['s3'] && $storage['working'])
                <p class="mt-2 text-sm text-ink"><span class="font-semibold text-club">&check; Working.</span> Photos and plan files are saved in the private S3 bucket <span class="font-mono">{{ $storage['bucket'] }}</span>, so they survive deploys.</p>
            @elseif ($storage['s3'])
                <p class="mt-2 text-sm text-red-700"><span class="font-semibold">S3 is turned on but not working.</span> Check the bucket name, the region, and that the server's role can use the bucket.</p>
                <p class="mt-1 font-mono text-xs text-ink/70">{{ $storage['problem'] }}</p>
            @else
                <p class="mt-2 text-sm text-ink/75"><span class="font-semibold text-amber-800">On the server's own disk.</span> Uploaded photos vanish every time you deploy. Set <span class="font-mono">UPLOADS_DISK=s3</span> and <span class="font-mono">AWS_BUCKET</span> to keep them.</p>
            @endif
        </section>

        {{-- Background jobs: emails and re-scoring after disputes and proof run here, after each save. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3 lg:col-span-2" data-queue-health>
            <h2 class="eyebrow text-club">Background jobs</h2>
            @if ($queue['waiting'] === null)
                <p class="mt-2 text-sm text-ink">Jobs run right away (<span class="font-mono">{{ $queue['connection'] }}</span> queue).</p>
            @elseif ($queue['healthy'])
                <p class="mt-2 text-sm text-ink"><span class="font-semibold text-club">&check; Worker running.</span> {{ $queue['waiting'] }} waiting{{ $queue['failed'] !== null ? ' · '.$queue['failed'].' failed in the last day' : '' }}.</p>
            @else
                <p class="mt-2 text-sm text-red-700"><span class="font-semibold">Jobs are piling up:</span> {{ $queue['waiting'] }} waiting, the oldest for {{ $queue['oldestMinutes'] }} minutes. The queue worker isn't running. Redeploy, or restart the <span class="font-mono">goalline-queue</span> service.</p>
            @endif
        </section>

        {{-- Outbound messaging switches (GOALLINE_EMAIL_*, GOALLINE_PUSH): what members are emailed right now. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3 lg:col-span-2" data-messaging-switches>
            <h2 class="eyebrow text-club">Outbound messages</h2>
            <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                @foreach ([
                    'GOALLINE_EMAIL_REMINDERS' => [App\Outbound::REMINDERS, 'Daily reminder emails'],
                    'GOALLINE_EMAIL_NUDGES' => [App\Outbound::NUDGES, 'Nudge and "Show your work" emails'],
                    'GOALLINE_EMAIL_RECAPS' => [App\Outbound::RECAPS, 'Recap and results emails'],
                    'GOALLINE_EMAIL_NOTIFICATIONS' => [App\Outbound::NOTIFICATIONS, 'Dispute, group task, friend and league emails'],
                    'GOALLINE_PUSH' => ['push', 'Push notifications'],
                ] as $name => [$key, $label])
                    @php $on = (bool) config('goalline.messaging.'.$key); @endphp
                    <li class="flex items-center justify-between gap-2">
                        <span class="text-ink">{{ $label }} <span class="font-mono text-[11px] text-ink/70">{{ $name }}</span></span>
                        <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-mint text-club' => $on, 'bg-ivory text-ink/70 ring-1 ring-line' => ! $on])>{{ $on ? 'On' : 'Paused' }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-ink/70">Paused emails still land in each member’s Inbox. Verification, password reset and emailed invites always send.</p>
        </section>

        {{-- AI spend. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3">
            <h2 class="eyebrow text-club">AI spend</h2>
            @foreach ([['Today', $ai['today'], $ai['dailyBudget']], ['This month', $ai['month'], $ai['monthlyBudget']]] as [$label, $spent, $limit])
                <div class="mt-3">
                    <div class="flex justify-between text-sm"><span class="text-ink/70">{{ $label }}</span><span class="font-semibold text-ink tabular-nums">{{ $money($spent) }} / {{ $money($limit) }}</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-mint">
                        <div @class(['h-full rounded-full', 'bg-grass' => $spent < $limit * 0.8, 'bg-amber-500' => $spent >= $limit * 0.8 && $spent < $limit, 'bg-red-500' => $spent >= $limit]) style="width: {{ min(100, $limit > 0 ? round($spent / $limit * 100) : 100) }}%"></div>
                    </div>
                </div>
            @endforeach
            <p class="mt-3 text-xs text-ink/70">Estimates from token counts. AI pauses for everyone at either limit; each member is also capped at {{ config('goalline.ai.daily_requests_per_member') }} AI requests a day.</p>
            @if ($ai['byKind'])
                <table class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @foreach ($ai['byKind'] as $row)
                            <tr><td class="py-1.5 text-ink/75">{{ Str::headline($row['kind']) }}</td><td class="py-1.5 text-right text-ink/70 tabular-nums">{{ $row['requests'] }} calls</td><td class="py-1.5 text-right font-semibold text-ink tabular-nums">{{ $money($row['cost']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if ($ai['topMembersToday'])
                <p class="mt-3 text-xs text-ink/70">Most AI today: {{ collect($ai['topMembersToday'])->map(fn ($row) => $row['name'].' ('.$row['requests'].')')->join(', ') }}</p>
            @endif
        </section>

        {{-- Feature use. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3 lg:col-span-2">
            <h2 class="eyebrow text-club">What people used, last 7 days</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs text-ink/70">
                    <tr><th class="py-1.5 font-semibold">Feature</th><th class="py-1.5 text-right font-semibold">Uses</th><th class="py-1.5 text-right font-semibold">Members</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($features as $feature)
                        <tr @class(['text-ink/70' => $feature['uses'] === 0])>
                            <td class="py-1.5">{{ $feature['label'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $feature['uses'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $feature['members'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        {{-- Beta feedback. --}}
        <section id="feedback" class="scroll-mt-6 rounded-2xl border border-line bg-white px-4 py-3 lg:col-span-2">
            <div class="flex items-end justify-between gap-4">
                <h2 class="eyebrow text-club">Beta feedback</h2>
                <span class="text-sm text-ink/70">{{ $feedback->whereNull('resolved_at')->count() }} open</span>
            </div>
            @if ($feedback->isEmpty())
                <p class="mt-3 text-sm text-ink/70">No notes yet. Testers send them with the Feedback button at the bottom right of every page.</p>
            @else
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($feedback as $note)
                        <li @class(['flex flex-wrap items-start gap-3 py-3', 'opacity-50' => $note->resolved_at])>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-ink/70">{{ $note->member ?? 'Former member' }} &middot; {{ App\Clock::shortTime(Carbon\Carbon::parse($note->created_at)) }}@if ($note->page) &middot; on <code>{{ $note->page }}</code>@endif</p>
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
        <section id="invites" class="scroll-mt-6 rounded-2xl border border-line bg-white px-4 py-3 lg:col-span-2">
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
                            <span class="min-w-0 flex-1 truncate text-ink/70">{{ $invite->note ?: 'No note' }}</span>
                            <span class="text-ink/70 tabular-nums">{{ $invite->uses }}/{{ $invite->max_uses }} used</span>
                            @if ($invite->isUsable())
                                <x-share-link :id="'beta-invite-'.$invite->code" :label="'Sign-up link for '.$invite->code" :url="$invite->signupUrl()" compact />
                                <form method="POST" action="{{ route('admin.invites.revoke', $invite) }}" onsubmit="return confirm('Revoke this invite? Nobody new can join with it.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-ink/70 hover:text-red-700">Revoke</button>
                                </form>
                            @else
                                <span class="eyebrow rounded-full bg-ivory px-2 py-0.5 text-[11px] text-ink/70">{{ $invite->revoked_at ? 'Revoked' : 'Used up' }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>

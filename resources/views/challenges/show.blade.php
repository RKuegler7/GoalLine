{{-- A Challenge, in Challenge gold: today's check-in for its type (and yesterday's, while it's open), your progress,
     the rules, the Standings (completion, never stars), members and invites, and the owner's tools. --}}
@php
    $progress = $mine ?? $challenge->progressFor($me->id);
    $yesterday = $today->subDay();
    $showYesterday = $challenge->isCheckableOn($yesterday) && ! $challenge->isCheckedIn($me, $yesterday)
        && ! ($challenge->sundays_off && $yesterday->isSunday()) && ! $challenge->isWeekly();
    $isOwner = $challenge->creator_id === $me->id;
    $unit = $progress['unit'];
    $when = match (true) {
        ! $challenge->hasStarted() => 'starts '.$challenge->starts_on->format('l, M j'),
        $challenge->isOver() => 'finished '.$challenge->ends_on->format('M j'),
        default => 'day '.$challenge->dayNumber().' of '.$challenge->lengthInDays(),
    };
@endphp

<x-layouts.app :title="$challenge->title.' · Challenge · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$challenge->title" :back="$challenge->league ? route('leagues.show', $challenge->league) : route('leagues.index', ['tab' => 'challenges'])"
            :back-label="$challenge->league?->name ?? 'Challenges'" :eyebrow="'Challenge · '.$challenge->typeLabel().' · '.$when"
            :subtitle="$challenge->starts_on->format('M j').' to '.$challenge->ends_on->format('M j, Y').' · '.$challenge->members->count().' '.Str::plural('member', $challenge->members->count())" />
    </x-slot:hero>

    <div class="space-y-4" data-first-content data-challenge="{{ $challenge->id }}">
        @if ($challenge->league)
            <p class="rounded-xl border border-gold/60 bg-gold-light/30 px-3 py-2 text-[13px] text-ink">A side Challenge for <a href="{{ route('leagues.show', $challenge->league) }}" class="font-semibold text-gold-ink underline-offset-2 hover:underline">{{ $challenge->league->name }}</a>. It never changes GoalLeague stars or Standings.</p>
        @endif

        <div class="grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-4">
                {{-- Check in, or what happens before kickoff. --}}
                <section aria-labelledby="checkin-title" class="rounded-2xl border border-gold/60 bg-white p-4" data-region="challenge-checkin-{{ $challenge->id }}">
                    @if (! $challenge->hasStarted())
                        <h2 id="checkin-title" class="eyebrow text-gold-ink">Kickoff</h2>
                        <p class="mt-1.5 text-[17px] font-bold text-ink">Starts {{ $challenge->starts_on->format('l, M j') }} at 12:00 a.m. New York time.</p>
                        <p class="mt-1 text-[13px] text-ink/75">Check-ins open then. Until kickoff the owner can still change the rules; after that they're locked.</p>
                    @elseif ($challenge->isOver())
                        <h2 id="checkin-title" class="eyebrow text-gold-ink">Final</h2>
                        <p class="mt-1.5 text-[17px] font-bold text-ink">{{ $progress['finished'] ? 'Challenge complete. Well done.' : 'This Challenge is over.' }}</p>
                        <p class="mt-1 text-[13px] text-ink/75">You finished {{ $progress['completed'] }} of {{ $progress['total'] }} {{ Str::plural($unit, $progress['total']) }}.</p>
                    @else
                        <h2 id="checkin-title" class="eyebrow text-gold-ink">Today &middot; {{ $today->format('l, M j') }}</h2>
                        @if ($challenge->sundays_off && $today->isSunday() && ! $challenge->isWeekly())
                            <p class="mt-1.5 text-[15px] text-ink">Sunday off. Rest up; your streak is safe.</p>
                        @else
                            <div class="mt-2">@include('challenges.partials.check-in', ['day' => $today])</div>
                        @endif
                        @if ($showYesterday)
                            <details class="mt-3 border-t border-line pt-2">
                                <summary class="inline-flex min-h-11 cursor-pointer items-center text-[13px] font-semibold text-gold-ink">Forgot yesterday? Check in for {{ $yesterday->format('l') }}</summary>
                                <div class="mt-2">@include('challenges.partials.check-in', ['day' => $yesterday])</div>
                            </details>
                        @endif
                    @endif
                </section>

                {{-- Your progress. --}}
                <section aria-labelledby="progress-title" class="rounded-2xl border border-line bg-white p-4" data-region="challenge-progress-{{ $challenge->id }}">
                    <h2 id="progress-title" class="eyebrow text-gold-ink">Your progress</h2>
                    <p class="mt-1 text-[28px] leading-none font-bold text-ink tabular-nums">{{ $progress['completed'] }}<span class="text-[17px] text-ink/70"> of {{ $progress['total'] }} {{ Str::plural($unit, $progress['total']) }}</span></p>
                    <x-progress-bar :percent="$progress['percent']" tone="gold" class="mt-2.5" label="Your completion" />
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-ivory px-1 py-2"><dt class="text-[11px] font-semibold text-ink/70">Completion</dt><dd class="text-[17px] font-bold tabular-nums">{{ $progress['percent'] }}%</dd></div>
                        <div class="rounded-lg bg-ivory px-1 py-2"><dt class="text-[11px] font-semibold text-ink/70">Streak</dt><dd class="text-[17px] font-bold tabular-nums">{{ $progress['streak'] }}</dd></div>
                        <div class="rounded-lg bg-ivory px-1 py-2"><dt class="text-[11px] font-semibold text-ink/70">{{ $challenge->isStrict() ? 'Restarts' : 'Misses used' }}</dt><dd class="text-[17px] font-bold tabular-nums">{{ $challenge->isStrict() ? $progress['restarts'] : $progress['missesUsed'].' of '.$challenge->allowed_misses }}</dd></div>
                    </dl>
                </section>

                {{-- Standings: completion, never stars. --}}
                <section aria-labelledby="standings-title" data-region="challenge-standings-{{ $challenge->id }}">
                    <h2 id="standings-title" class="eyebrow mb-1.5 text-gold-ink">Standings</h2>
                    <x-challenge-table :challenge="$challenge" :rows="$standings" :me="$me" :column="$column" />
                </section>
            </div>

            <aside class="space-y-4">
                {{-- The rules. --}}
                <section aria-labelledby="rules-title" class="rounded-2xl border border-line bg-white p-4 text-[13px]">
                    <h2 id="rules-title" class="eyebrow text-gold-ink">The rules</h2>
                    <p class="mt-1.5 text-[15px] font-semibold text-ink">{{ $challenge->typeLabel() }}: {{ $challenge->ruleSummary() }}</p>
                    <p class="mt-0.5 text-ink/75">{{ $challenge->typeExplanation() }}</p>
                    @if ($challenge->type === App\Models\Challenge::DAILY && count($challenge->checklist()) > 1)
                        <ul class="mt-1.5 list-inside list-disc text-ink/85">
                            @foreach ($challenge->checklist() as $rule)<li>{{ $rule }}</li>@endforeach
                        </ul>
                    @endif
                    <ul class="mt-2 space-y-1 text-ink/80">
                        <li>{{ $challenge->modeLabel() }}.</li>
                        @if ($challenge->sundays_off)<li>Sundays off: not required, and they never break a streak.</li>@endif
                        <li>{{ $challenge->is_public ? 'Public: anyone can find and join.' : 'Private: invite only.' }}{{ $challenge->max_members ? ' Up to '.$challenge->max_members.' members.' : '' }}</li>
                        <li>Check in today or yesterday. {{ $challenge->rulesLocked() ? 'The rules are locked.' : 'The rules lock at kickoff.' }}</li>
                    </ul>
                    @if ($challenge->description)
                        <p class="mt-2 whitespace-pre-line text-ink/80">{{ $challenge->description }}</p>
                    @endif
                </section>

                {{-- Bring people in. --}}
                @unless ($challenge->isOver())
                    <section id="invite" aria-labelledby="invite-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4">
                        <h2 id="invite-title" class="eyebrow text-gold-ink">Bring people in</h2>
                        @if ($challenge->league)
                            <p class="mt-1.5 text-[13px] text-ink/75">Members of {{ $challenge->league->name }} can opt in from the GoalLeague page, or with this link.</p>
                        @else
                            <p class="mt-1.5 font-mono text-2xl font-semibold tracking-widest text-ink">{{ $challenge->invite_code }}</p>
                        @endif
                        <x-share-link id="challenge-link" label="Or share this link" :url="$challenge->joinUrl()" class="mt-2"
                            :share-title="'Join '.$challenge->title.' on GoalLine'" :share-message="'Join my GoalLine Challenge, '.$challenge->title.': '.$challenge->joinUrl()" />
                        <form method="POST" action="{{ route('challenges.invites.store', $challenge) }}" class="mt-3">
                            @csrf
                            <label for="challenge-invite-who" class="text-[13px] font-semibold text-ink/80">Invite by username</label>
                            <div class="mt-1 flex gap-2">
                                <input id="challenge-invite-who" name="who" required autocomplete="off" autocapitalize="none" placeholder="@username" value="{{ old('who') }}"
                                    class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-[15px] outline-none focus:border-gold-ink focus:ring-4 focus:ring-gold/20">
                                <button type="submit" class="min-h-11 rounded-full bg-gold px-4 text-[15px] font-semibold text-ink">Invite</button>
                            </div>
                            @error('who', 'invite') <p class="mt-1 text-[13px] text-red-700">{{ $message }}</p> @enderror
                        </form>
                        @if ($pendingInvites->isNotEmpty())
                            <p class="mt-2 text-[13px] text-ink/75">Waiting on: {{ $pendingInvites->map(fn ($invite) => $invite->invitee->name)->implode(', ') }}.</p>
                        @endif
                    </section>
                @endunless

                {{-- Members. --}}
                <section id="members" aria-labelledby="members-title" class="scroll-mt-20 rounded-2xl border border-line bg-white p-4">
                    <h2 id="members-title" class="eyebrow text-gold-ink">Members</h2>
                    <ul class="mt-2 divide-y divide-line">
                        @foreach ($challenge->members as $member)
                            <li class="flex items-center gap-2 py-1.5">
                                <a href="{{ $member->user->profileUrl() }}" class="flex min-h-11 min-w-0 flex-1 items-center gap-2 hover:text-gold-ink" data-member-profile>
                                    <x-avatar :user="$member->user" class="size-8 text-[11px]" />
                                    <span class="min-w-0 text-[15px] [overflow-wrap:anywhere]">{{ $member->user->name }}{{ $member->user_id === $challenge->creator_id ? ' (owner)' : '' }}</span>
                                </a>
                                @if ($isOwner && $member->user_id !== $challenge->creator_id)
                                    <form method="POST" action="{{ route('challenges.members.destroy', [$challenge, $member]) }}" onsubmit="return confirm('Remove {{ e($member->user->name) }} from the Challenge? Their check-ins go too.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="min-h-11 px-2 text-[13px] font-semibold text-ink/70 hover:text-red-700">Remove</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Disputes for the owner to decide. --}}
                @if ($isOwner && $disputes->isNotEmpty())
                    <section id="disputes" aria-labelledby="disputes-title" class="scroll-mt-20 rounded-2xl border border-gold bg-white p-4">
                        <h2 id="disputes-title" class="eyebrow text-gold-ink">Disputes to decide</h2>
                        <ul class="mt-2 space-y-3">
                            @foreach ($disputes as $dispute)
                                <li class="text-[13px]">
                                    <p class="text-ink"><span class="font-semibold">{{ $dispute->disputer->name }}</span> questions {{ $dispute->checkIn->user->name }}'s check-in for {{ $dispute->checkIn->checked_on->format('D, M j') }}{{ $dispute->reason ? ': "'.$dispute->reason.'"' : '.' }}</p>
                                    <div class="mt-1 flex gap-2">
                                        @foreach (['upheld' => 'Uphold (doesn\'t count)', 'dismissed' => 'Dismiss (it counts)'] as $resolution => $text)
                                            <form method="POST" action="{{ route('challenges.disputes.resolve', [$challenge, $dispute]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="resolution" value="{{ $resolution }}">
                                                <button type="submit" class="min-h-11 rounded-full border border-line px-3 font-semibold text-ink hover:border-gold">{{ $text }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Owner tools, or leaving. --}}
                <section class="space-y-1 text-[13px] text-ink/75">
                    <p>Started by {{ $challenge->creator->name }}.</p>
                    @if ($isOwner)
                        <a href="{{ route('challenges.edit', $challenge) }}" class="inline-flex min-h-11 items-center font-semibold text-gold-ink hover:underline">{{ $challenge->rulesLocked() ? 'Edit name, description or visibility' : 'Edit the Challenge' }}</a>
                        @can('delete', $challenge)
                            <form method="POST" action="{{ route('challenges.destroy', $challenge) }}" onsubmit="return confirm('Call off this Challenge for everyone?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="min-h-11 font-semibold text-ink/70 hover:text-red-700">Call off the Challenge</button>
                            </form>
                        @endcan
                    @elseif (! $challenge->isOver())
                        <form method="POST" action="{{ route('challenges.leave', $challenge) }}" onsubmit="return confirm('Leave this Challenge? Your check-ins go with you.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="min-h-11 font-semibold text-ink/70 hover:text-red-700">Leave the Challenge</button>
                        </form>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-layouts.app>

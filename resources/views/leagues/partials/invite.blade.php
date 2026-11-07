{{-- Invite: a shareable link and code, invites by email or username, people search, and, for the owner, pending
     invites and members. Always here, so a new league never leaves you with nowhere to invite people. --}}
@php
    $isOwner = $league->commissioner_id === auth()->id();
    $joinable = $league->isJoinable();
    $inviteErrors = $errors->getBag('invite');
@endphp
<section id="invite" class="mb-6 scroll-mt-6 rounded-2xl border border-line bg-white px-6 py-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="eyebrow text-club">Invite your people</h2>
        <span class="text-sm text-ink/55">{{ $league->members->count() }} {{ Str::plural('member', $league->members->count()) }}</span>
    </div>

    @if (! $joinable)
        <p class="mt-2 text-sm text-ink/65">
            {{ $status === App\LeagueStatus::Forming ? 'This league is full.' : 'This season has kicked off, so the schedule is locked and new members can\'t join. Start a new GoalLeague to bring more people in.' }}
        </p>
    @else
        <div class="mt-3 grid gap-4 sm:grid-cols-[auto_1fr] sm:items-end">
            <div>
                <p class="text-sm text-ink/60">Invite code</p>
                <div class="flex items-center gap-2">
                    <p class="font-mono text-3xl font-semibold tracking-[0.2em] text-club">{{ $league->invite_code }}</p>
                    <button type="button" data-copy="{{ $league->invite_code }}" class="rounded-full border border-line px-3 py-1 text-xs font-semibold text-club hover:border-club/40">Copy</button>
                </div>
            </div>
            <div class="min-w-0 sm:border-l sm:border-line sm:pl-4">
                <label for="invite-link" class="text-sm text-ink/60">Invite link</label>
                <div class="mt-1 flex gap-2">
                    <input id="invite-link" readonly value="{{ $shareInvite->url() }}" onclick="this.select()"
                        class="block min-w-0 flex-1 rounded-lg border border-line bg-ivory px-3 py-2 text-sm text-ink/80 outline-none focus:border-club">
                    <button type="button" data-copy="{{ $shareInvite->url() }}" class="rounded-lg border border-line px-3 py-2 text-sm font-semibold text-club hover:border-club/40">Copy</button>
                    <button type="button" data-share-url="{{ $shareInvite->url() }}" data-share-title="Join {{ $league->name }} on GoalLine"
                        data-share-text="Join my GoalLeague {{ $league->name }} on GoalLine. We get more done together." hidden
                        class="rounded-lg bg-club px-3 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Share</button>
                </div>
                <p class="mt-1 text-xs text-ink/50">
                    {{ $shareInvite->expires_at ? 'Expires '.App\Clock::local($shareInvite->expires_at)->format('M j') : 'Never expires' }}{{ $shareInvite->max_uses ? ' · '.$shareInvite->uses.' of '.$shareInvite->max_uses.' uses' : '' }}.
                    People can join until kickoff.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('leagues.invites.store', $league) }}" data-live class="mt-5">
            @csrf
            <label for="invite-who" class="text-sm font-semibold text-ink/80">Invite by email or username</label>
            <div class="mt-1 flex gap-2">
                <input id="invite-who" name="who" required value="{{ old('who') }}" autocomplete="off" autocapitalize="none" placeholder="friend@email.com or @username"
                    class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                <button type="submit" class="rounded-lg bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Send invite</button>
            </div>
            @if ($inviteErrors->any())
                <p class="mt-1.5 text-sm text-red-700">{{ $inviteErrors->first() }}</p>
            @else
                <p class="mt-1 text-xs text-ink/50">Email invites arrive by email; username invites land in their GoalLine inbox. Each works once and expires in {{ App\Models\LeagueInvite::DEFAULT_EXPIRY_DAYS }} days.</p>
            @endif
        </form>

        <x-people-search-form :league="$league" label="Find friends to invite" class="mt-4 border-dashed bg-ivory/50" />
    @endif

    @if ($isOwner)
        <details class="mt-5 border-t border-line pt-4" @if ($pendingInvites->isNotEmpty() || session('status') && str_contains(session('status'), 'invite')) open @endif>
            <summary class="cursor-pointer list-none text-sm font-semibold text-club hover:underline [&::-webkit-details-marker]:hidden">Manage invites and members</summary>

            @if ($joinable)
                <form method="POST" action="{{ route('leagues.invites.regenerate', $league) }}" class="mt-3 rounded-xl bg-ivory px-4 py-3 text-sm">
                    @csrf
                    <p class="font-semibold text-ink/80">New invite link</p>
                    <p class="text-xs text-ink/55">The current link stops working.</p>
                    <div class="mt-2 flex flex-wrap items-end gap-3">
                        <div>
                            <label for="link-expiry" class="block text-xs text-ink/60">Expires</label>
                            <select id="link-expiry" name="expires_in_days" class="rounded-lg border border-line bg-white px-2 py-1.5 text-sm">
                                @foreach (App\Models\LeagueInvite::EXPIRY_CHOICES as $days)
                                    <option value="{{ $days }}" @selected($days === App\Models\LeagueInvite::DEFAULT_EXPIRY_DAYS)>{{ $days ? 'In '.$days.' '.Str::plural('day', $days) : 'Never' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="link-uses" class="block text-xs text-ink/60">Max uses (optional)</label>
                            <input id="link-uses" name="max_uses" type="number" min="1" max="{{ App\Models\League::MAX_MEMBERS }}" placeholder="No limit" class="w-28 rounded-lg border border-line bg-white px-2 py-1.5 text-sm">
                        </div>
                        <label class="flex items-center gap-1.5 text-xs text-ink/70"><input type="checkbox" name="new_code" value="1"> New code too</label>
                        <button type="submit" class="rounded-full border border-club/40 px-4 py-1.5 text-sm font-semibold text-club hover:border-club">Make new link</button>
                    </div>
                </form>
            @endif

            <h3 class="mt-4 text-xs font-semibold text-ink/60 uppercase">Pending invites</h3>
            @forelse ($pendingInvites as $pending)
                <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                    <span class="min-w-0 truncate text-ink/80">{{ $pending->recipientLabel() }} <span class="text-ink/45">&middot; expires {{ App\Clock::local($pending->expires_at)?->format('M j') ?? 'never' }}</span></span>
                    <form method="POST" action="{{ route('leagues.invites.revoke', $pending) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-ink/50 hover:text-red-700">Revoke</button>
                    </form>
                </div>
            @empty
                <p class="mt-1 text-sm text-ink/50">None right now.</p>
            @endforelse

            <h3 class="mt-4 text-xs font-semibold text-ink/60 uppercase">Members</h3>
            <ul class="mt-1 space-y-1.5">
                @foreach ($league->members as $leagueMember)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="flex min-w-0 items-center gap-2"><x-avatar :user="$leagueMember->user" class="size-6 text-[0.55rem]" /> <span class="truncate">{{ $leagueMember->user->name }}</span>
                            @if ($leagueMember->user_id === $league->commissioner_id)
                                <span class="text-xs text-ink/45">Owner</span>
                            @endif
                        </span>
                        @if ($leagueMember->user_id !== $league->commissioner_id)
                            <form method="POST" action="{{ route('leagues.members.destroy', [$league, $leagueMember]) }}" onsubmit="return confirm('Remove {{ addslashes($leagueMember->user->name) }} from the league?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-ink/50 hover:text-red-700">Remove</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</section>

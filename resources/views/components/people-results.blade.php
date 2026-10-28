{{-- People search results: avatar, name and username only (never emails), with Add friend, Invite to league, and View. --}}
@props(['results', 'query' => '', 'league' => null])

@php
    $leagues = $league ? collect([$league]) : App\Http\Controllers\PeopleSearchController::invitableLeagues(auth()->user());
@endphp

@if (mb_strlen($query) >= 2)
    @if ($results->isEmpty())
        <p class="mt-3 rounded-2xl border border-line bg-white px-5 py-4 text-sm text-ink/65">No one found for &ldquo;{{ $query }}&rdquo;. Try their exact username or email, or send an invite by email.</p>
    @else
        <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
            @foreach ($results as $person)
                @php
                    $openLeagues = $leagues->reject(fn ($candidate) => $candidate->members->contains('user_id', $person->id));
                @endphp
                <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <x-avatar :user="$person" class="size-10 text-xs" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-ink">{{ $person->name }}</span>
                        <span class="block truncate text-sm text-ink/55">{{ '@'.$person->username }}</span>
                    </span>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-friend-button :user="$person" />
                        @if ($openLeagues->isNotEmpty())
                            <form method="POST" action="{{ route('leagues.invites.store', $openLeagues->first()) }}" class="flex items-center gap-1" data-invite-form>
                                @csrf
                                <input type="hidden" name="who" value="{{ '@'.$person->username }}">
                                @if ($openLeagues->count() > 1)
                                    <label for="invite-league-{{ $person->id }}" class="sr-only">League</label>
                                    <select id="invite-league-{{ $person->id }}" class="rounded-full border border-line bg-white px-2 py-1.5 text-xs"
                                        onchange="this.form.action = this.value">
                                        @foreach ($openLeagues as $openLeague)
                                            <option value="{{ route('leagues.invites.store', $openLeague) }}">{{ $openLeague->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button type="submit" class="rounded-full border border-club/30 px-4 py-2 text-sm font-semibold text-club hover:border-club">Invite to league</button>
                            </form>
                        @endif
                        <a href="{{ $person->profileUrl() }}" class="rounded-full px-3 py-2 text-sm font-semibold text-ink/60 hover:text-ink">View</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endif

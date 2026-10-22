{{-- Chat messages and league updates. Also returned on its own for live updates. --}}
@foreach ($messages as $message)
    @if ($message->is_system)
        <li data-message-id="{{ $message->id }}" class="flex items-center justify-center gap-2 py-2 text-center text-sm text-ink/60">
            <span class="size-1.5 shrink-0 rounded-full bg-grass" aria-hidden="true"></span>
            <span>{{ $message->body }}</span>
            <time datetime="{{ $message->created_at->toIso8601String() }}" class="shrink-0 text-xs text-ink/40">{{ App\Clock::shortTime($message->created_at) }}</time>
        </li>
    @else
        <li data-message-id="{{ $message->id }}" class="group flex items-start gap-3 py-2">
            @if ($message->user)
                <x-avatar :user="$message->user" class="mt-0.5 size-8 text-[0.65rem]" />
            @else
                <span class="mt-0.5 size-8 shrink-0 rounded-full bg-line" aria-hidden="true"></span>
            @endif
            <div class="min-w-0 flex-1">
                <p class="flex items-baseline gap-2">
                    @if ($message->user)
                        <a href="{{ $message->user->profileUrl() }}" class="font-semibold text-ink hover:text-club hover:underline">{{ $message->user->name }}</a>
                    @else
                        <span class="font-semibold text-ink">Former member</span>
                    @endif
                    <time datetime="{{ $message->created_at->toIso8601String() }}" class="text-xs text-ink/45">
                        {{ App\Clock::shortTime($message->created_at) }}
                    </time>
                </p>
                <p class="break-words whitespace-pre-line text-ink/85">{{ App\Mentions::render($message->body) }}</p>
            </div>
            @if (auth()->id() === $message->user_id || auth()->id() === $league->commissioner_id)
                <form method="POST" action="{{ route('leagues.messages.destroy', $message) }}" data-chat-delete class="shrink-0 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded px-1.5 text-sm text-ink/40 hover:text-red-700" title="Delete message">&times;<span class="sr-only">Delete message</span></button>
                </form>
            @endif
        </li>
    @endif
@endforeach

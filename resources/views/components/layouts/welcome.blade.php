{{-- The welcome walkthrough: four steps along the signature Line, without the app's tabs. --}}
@props(['step', 'title', 'intro' => null])

@php
    $steps = ['You', 'Your goal', 'Your crew', 'The rules'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title.' · Welcome to GoalLine'])
</head>
<body class="min-h-screen bg-ivory font-sans text-ink antialiased">
    <section class="relative overflow-hidden bg-club">
        <x-field-backdrop class="absolute inset-0 size-full" />
        <div class="relative mx-auto max-w-2xl px-4 pt-6 pb-10">
            <div class="flex items-center justify-between">
                <x-brand.logo class="h-7" />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-ivory/70 hover:text-ivory">Log out</button>
                </form>
            </div>

            {{-- Progress, drawn as the GoalLine: done steps in grass, a marker where you are, the post at the end. --}}
            <ol class="mt-8 flex items-center" aria-label="Welcome steps">
                @foreach ($steps as $index => $label)
                    @php
                        $number = $index + 1;
                    @endphp
                    <li class="flex flex-1 items-center">
                        <span @class([
                            'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            'bg-grass text-white' => $number < $step,
                            'bg-ivory text-club ring-4 ring-grass/50' => $number === $step,
                            'bg-ivory/20 text-ivory/70' => $number > $step,
                        ]) @if ($number === $step) aria-current="step" @endif>
                            <span class="sr-only">Step {{ $number }}: {{ $label }}</span>
                            <span aria-hidden="true">{{ $number }}</span>
                        </span>
                        <span @class(['mx-1.5 h-1 flex-1 rounded-full', 'bg-grass' => $number < $step, 'bg-ivory/25' => $number >= $step])></span>
                    </li>
                @endforeach
                <li class="h-6 w-1 shrink-0 rounded-full bg-ivory/70" aria-hidden="true"></li>
            </ol>
            <p class="eyebrow mt-3 text-mint">Step {{ $step }} of {{ count($steps) }} &middot; {{ $steps[$step - 1] }}</p>

            <h1 class="mt-4 font-serif text-4xl font-medium tracking-tight text-ivory">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-2 text-ivory/85">{{ $intro }}</p>
            @endif
        </div>
    </section>
    <x-goal-line />

    <main class="mx-auto max-w-2xl px-4 py-8">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-xl border border-grass/30 bg-mint px-4 py-3 text-sm font-medium text-club">{{ session('status') }}</p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>

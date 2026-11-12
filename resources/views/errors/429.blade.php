@extends('errors.layout')

@php
    // How long to wait, from the limiter's Retry-After (a minute if it didn't say).
    $wait = max(5, (int) (isset($exception) ? ($exception->getHeaders()['Retry-After'] ?? 60) : 60));
@endphp

@section('code', '429')
@section('title', 'Lots of people are trying GoalLine right now')
@section('message')
    Give it a moment, then try again. <span data-retry-text>You can try again in <span data-retry-seconds>{{ $wait }}</span> seconds.</span>
@endsection

@section('actions')
    <a href="{{ url()->current() === url('/demo') ? url('/demo') : url()->previous('/') }}" data-retry-button
        class="inline-flex min-h-11 items-center rounded-full bg-grass px-5 text-sm font-bold text-ink hover:brightness-105">Try again</a>
    <script>
        (() => {
            const seconds = document.querySelector('[data-retry-seconds]');
            const text = document.querySelector('[data-retry-text]');
            let left = Number(seconds?.textContent || 0);
            const tick = () => {
                left -= 1;
                if (left <= 0) {
                    text.textContent = 'You can try again now.';
                    return;
                }
                seconds.textContent = String(left);
                window.setTimeout(tick, 1000);
            };
            if (seconds && left > 0) {
                window.setTimeout(tick, 1000);
            }
        })();
    </script>
@endsection

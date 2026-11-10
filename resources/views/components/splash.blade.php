{{-- The full-screen splash: the wordmark and the line, on the first page of a visit only (once per session). It never
     holds anyone up: it leaves as soon as the page is ready, and by about 1.2 seconds at the latest, even without
     JavaScript (app.css). A tap skips it. --}}
@php
    $showSplash = ! session()->has('splash_seen') && ! request()->ajax();

    if ($showSplash) {
        session()->put('splash_seen', true);
    }
@endphp

@if ($showSplash)
    <div data-splash class="gl-splash" aria-hidden="true">
        <x-line-loader size="splash" label="Loading GoalLine" :delayed="false" />
    </div>
@endif

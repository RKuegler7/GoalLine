{{-- Link previews for iMessage, WhatsApp, Slack and social apps: Open Graph and Twitter card tags. Every URL is
     absolute, built from APP_URL. Include with ['title' => …, 'description' => …, 'url' => …]. --}}
@php
    $image = App\ShareLink::to('/icons/icon-512.png');
@endphp
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $url }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GoalLine">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta property="og:image:alt" content="The GoalLine GL icon">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">

{{-- Link previews for iMessage, WhatsApp, Slack and social apps: Open Graph and Twitter card tags. Every URL is
     absolute, built from APP_URL. Include with ['title' => …, 'description' => …, 'url' => …], and optionally a big
     picture: ['image' => absolute URL, 'imageWidth' => …, 'imageHeight' => …, 'imageAlt' => …]. --}}
@php
    $hasImage = isset($image);
    $image = $hasImage ? $image : App\ShareLink::to('/icons/icon-512.png');
    $imageWidth = $hasImage ? ($imageWidth ?? 1080) : 512;
    $imageHeight = $hasImage ? ($imageHeight ?? 1350) : 512;
    $imageAlt = $hasImage ? ($imageAlt ?? $title) : 'The GoalLine GL icon';
@endphp
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $url }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GoalLine">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="{{ $imageWidth }}">
<meta property="og:image:height" content="{{ $imageHeight }}">
<meta property="og:image:alt" content="{{ $imageAlt }}">
<meta name="twitter:card" content="{{ $hasImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<meta name="twitter:image:alt" content="{{ $imageAlt }}">

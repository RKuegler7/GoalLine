<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GoalLine — Coming Soon</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #0f2a1d;
            color: #f4f7f2;
            text-align: center;
        }
        main { max-width: 640px; }
        h1 {
            margin: 0 0 16px;
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            letter-spacing: -0.02em;
        }
        p.tagline {
            margin: 0 0 40px;
            font-size: clamp(1.1rem, 3vw, 1.35rem);
            line-height: 1.5;
            color: #cfe0d4;
        }
        .soon {
            display: inline-block;
            padding: 10px 24px;
            border: 2px solid #f4f7f2;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <main>
        <h1>GoalLine</h1>
        <p class="tagline">A fantasy league for goals you're actually trying to accomplish.</p>
        <span class="soon">Coming Soon</span>
    </main>
</body>
</html>

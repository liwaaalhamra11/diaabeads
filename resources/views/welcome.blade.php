@php
    // Use the first logo file found in public/images (logo.svg, logo.png, …).
    $logo = collect(['svg', 'png', 'webp', 'jpg'])
        ->map(fn ($ext) => "images/logo.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Crafted for you</title>
    <meta name="description" content="Crafted for you. Elegance — where your style matters.">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Jost:wght@300;400&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Black & white like the logo, bronze as the only accent. */
            --bg: #ffffff;
            --ink: #0a0a0a;
            --muted: #6b6b6b;
            --bronze: #9b7346;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 48px 16px;
            background:
                radial-gradient(ellipse at 50% 35%, rgba(155, 115, 70, .08), transparent 60%),
                var(--bg);
            color: var(--ink);
            font-family: 'Jost', system-ui, sans-serif;
            font-weight: 300;
            text-align: center;
            overflow-x: hidden;
        }

        main {
            width: 100%;
            max-width: 680px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo {
            display: block;
            width: min(340px, 78vw);
            height: auto;
            animation: rise 1.1s cubic-bezier(.2, .7, .2, 1) both;
        }

        .wordmark {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            font-size: clamp(3rem, 11vw, 5rem);
            line-height: 1;
            letter-spacing: .01em;
            animation: rise 1.1s cubic-bezier(.2, .7, .2, 1) both;
        }

        .rule {
            width: 56px;
            height: 1px;
            background: var(--bronze);
            margin: 40px 0 32px;
            animation: grow 1s .45s cubic-bezier(.2, .7, .2, 1) both;
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 500;
            font-size: clamp(2.1rem, 7vw, 3.4rem);
            line-height: 1.15;
            animation: rise 1.1s .3s cubic-bezier(.2, .7, .2, 1) both;
        }

        h1 em {
            font-style: italic;
            color: var(--bronze);
        }

        p {
            margin-top: 18px;
            font-size: clamp(.8rem, 2.4vw, .95rem);
            letter-spacing: .32em;
            text-transform: uppercase;
            color: var(--muted);
            animation: rise 1.1s .5s cubic-bezier(.2, .7, .2, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: none; }
        }

        @keyframes grow {
            from { opacity: 0; transform: scaleX(0); }
            to { opacity: 1; transform: scaleX(1); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; }
        }
    </style>
</head>
<body>
    <main>
        @if ($logo)
            <img class="logo" src="{{ asset($logo) }}" alt="{{ config('app.name') }}">
        @else
            {{-- Shown until a logo is added to public/images (logo.svg / .png / .webp / .jpg) --}}
            <div class="wordmark" role="img" aria-label="{{ config('app.name') }}">{{ config('app.name') }}</div>
        @endif

        <div class="rule" aria-hidden="true"></div>

        <h1>Crafted for you <em>Elegance</em></h1>
        <p>Where your style matters</p>
    </main>
</body>
</html>

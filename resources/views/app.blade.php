<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.description') }}">
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        {{-- Internal HR system with personal and salary data: keep it out of search engines. --}}
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        {{-- Stop phones turning CNICs, PINs and amounts into phone-number links. --}}
        <meta name="format-detection" content="telephone=no">
        <meta name="theme-color" content="#fcfcfc" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#121212" media="(prefers-color-scheme: dark)">

        {{-- Link previews when a page URL is shared --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ config('app.name') }}">
        <meta property="og:description" content="{{ config('app.description') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ config('app.name') }} logo">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ config('app.name') }}">
        <meta name="twitter:description" content="{{ config('app.description') }}">
        <meta name="twitter:image" content="{{ asset('og-image.png') }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.9911 0 0);
            }

            html.dark {
                background-color: oklch(0.1822 0 0);
            }
        </style>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

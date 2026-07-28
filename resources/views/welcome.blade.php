<!doctype html>
<html lang="de" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#17140f">
    <title>WolfCash</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center gap-6 bg-ink px-4 text-center font-sans text-fg antialiased">

<span class="inline-block h-3 w-3 rounded-full bg-accent"></span>

<div>
    <h1 class="font-display text-3xl font-semibold tracking-tight">WolfCash</h1>
    <p class="mt-2 text-dim">Kassensystem für Gastronomie</p>
</div>

<a
    href="{{ route('login') }}"
    class="rounded-full bg-accent px-6 py-2.5 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong"
>
    Zum Login
</a>

</body>
</html>

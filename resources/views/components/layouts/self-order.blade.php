<!doctype html>
<html lang="de" class="dark">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta
        name="theme-color"
        content="#17140f"
    >

    <meta
        http-equiv="Content-Security-Policy"
        content="upgrade-insecure-requests"
    >

    <title>WolfCash Bestellung</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @livewireStyles
</head>

<body class="min-h-screen bg-ink font-sans text-fg antialiased">

<header
    class="sticky top-0 z-40 border-b border-line/70 bg-ink/95 backdrop-blur"
>
    <div
        class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3"
    >
        <div class="flex items-center gap-2">
            <span
                class="inline-block h-2.5 w-2.5 rounded-full bg-accent"
            ></span>

            <span
                class="font-display text-lg font-semibold tracking-tight"
            >
                WolfCash
            </span>
        </div>
    </div>
</header>

<main>
    {{ $slot }}
</main>

@livewireScripts

</body>
</html>

<!doctype html>
<html lang="de" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#17140f">
    <title>Login · WolfCash</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-ink px-4 font-sans text-fg antialiased">

<div class="w-full max-w-sm">

    <div class="mb-8 flex flex-col items-center gap-2 text-center">
        <span class="inline-block h-3 w-3 rounded-full bg-accent"></span>
        <h1 class="font-display text-2xl font-semibold tracking-tight">WolfCash</h1>
        <p class="text-sm text-dim">Bitte melde dich an</p>
    </div>

    <div class="rounded-2xl border border-line bg-surface p-6">
        <livewire:auth.login />
    </div>

</div>

@livewireScripts
</body>
</html>

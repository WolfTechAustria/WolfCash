<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>WolfCash Mobile</title>

    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-950 text-white">

<main
    class="flex min-h-screen
               items-center justify-center p-6"
>
    <div class="text-center">

        <div
            class="text-5xl font-bold
                       text-green-400"
        >
            ✓
        </div>

        <h1
            class="mt-4 text-2xl font-bold"
        >
            WolfCash verbunden
        </h1>

        <p
            class="mt-2 text-gray-400"
        >
            Geräte-Session erfolgreich.
        </p>

        <p
            class="mt-6 text-sm text-gray-500"
        >
            Device ID:
            {{ $deviceId }}
        </p>

    </div>
</main>

</body>
</html>

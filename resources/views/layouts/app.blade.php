<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kassensystem</title>

    @livewireStyles
</head>
<body>

<nav>
    <a href="/admin">Dashboard</a>
    <a href="/admin/devices">Devices</a>
</nav>

<main>
    {{ $slot }}
</main>

@livewireScripts
</body>
</html>

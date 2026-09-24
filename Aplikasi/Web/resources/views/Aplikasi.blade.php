<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @viteReactRefresh
    @vite(['resources/js/Aplikasi.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

@php
    $seo = $page['props']['Halaman']['Seo'] ?? [];
    $pratinjau = (bool) ($page['props']['Halaman']['Pratinjau'] ?? false);
    $namaSitus = $seo['NamaSitus'] ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- D-21: meta SEO & pratinjau tautan diisi server (tanpa SSR Node di hosting bersama). --}}
    <title inertia>{{ $seo['Judul'] ?? $namaSitus }}</title>
    <meta name="description" content="{{ $seo['Deskripsi'] ?? '' }}">
    @if (! empty($seo['KataKunci']))
        <meta name="keywords" content="{{ $seo['KataKunci'] }}">
    @endif
    @if ($pratinjau)
        <meta name="robots" content="noindex, nofollow">
    @elseif (! empty($seo['Kanonik']))
        <link rel="canonical" href="{{ $seo['Kanonik'] }}">
    @endif
    @if (! empty($seo['VerifikasiGoogle']))
        <meta name="google-site-verification" content="{{ $seo['VerifikasiGoogle'] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $namaSitus }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $seo['Judul'] ?? $namaSitus }}">
    <meta property="og:description" content="{{ $seo['Deskripsi'] ?? '' }}">
    @if (! empty($seo['Kanonik']))
        <meta property="og:url" content="{{ $seo['Kanonik'] }}">
    @endif
    @if (! empty($seo['Gambar']))
        <meta property="og:image" content="{{ $seo['Gambar'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $namaSitus,
        'url' => $seo['Kanonik'] ?? null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @viteReactRefresh
    @vite(['resources/js/Situs.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

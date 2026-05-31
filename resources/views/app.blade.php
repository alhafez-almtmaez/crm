<!DOCTYPE html>
@php
    $brandTagline = app(\App\Services\System\SystemSettingsService::class)->get()['brandTagline'] ?? '';
    $resolvedMeta = $pageMeta ?? [];
    $metaTitle = $resolvedMeta['title'] ?? config('app.name');
    $metaDescription = $resolvedMeta['description'] ?? ($brandTagline !== '' ? $brandTagline : config('app.name', 'Vita'));
    $metaUrl = $resolvedMeta['url'] ?? url()->current();
    $metaImage = $resolvedMeta['image'] ?? null;
    $metaType = $resolvedMeta['type'] ?? 'website';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" itemprop="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $metaUrl }}">
    @if($metaImage)
        <link rel="shortcut icon" href="{{ $metaImage }}">
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:secure_url" content="{{ $metaImage }}">
        <meta name="twitter:image" content="{{ $metaImage }}">
    @endif
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:type" content="{{ $metaType }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <title inertia>{{ $metaTitle }}</title>
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

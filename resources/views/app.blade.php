<!DOCTYPE html>
@php
    $brandTagline = app(\App\Services\System\SystemSettingsService::class)->get()['brandTagline'] ?? '';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $brandTagline !== '' ? $brandTagline : config('app.name', 'Vita') }}">
    <title inertia>{{ config('app.name') }}</title>
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <noscript>
        Esta aplicación requiere JavaScript habilitado.
    </noscript>
    <div id="app">
        <h1>{{ config('app.name', 'Laravel') }}</h1>
        <p>El frontend React (T-005) se monta aquí. Por ahora esto es el shell del SPA con el JS por defecto de Laravel.</p>
    </div>
</body>
</html>

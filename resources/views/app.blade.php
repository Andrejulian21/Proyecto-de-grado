<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    {{-- React Fast Refresh preamble (required by @vitejs/plugin-react v6+ with backend-served HTML) --}}
    @if(!app()->environment('production'))
    <script type="module">
        import RefreshRuntime from 'http://localhost:5173/@react-refresh'
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
    </script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    <noscript>
        Esta aplicación requiere JavaScript habilitado.
    </noscript>
    <div id="app"></div>
</body>
</html>

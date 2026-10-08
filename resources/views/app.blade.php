<!DOCTYPE html>
<html lang="id" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title ?? config('app.name') }}</title>
        <script>
            document.documentElement.dataset.theme = localStorage.getItem('appfeed-theme') || 'dark';
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-page font-sans text-sm text-tx antialiased">
        <div id="app"></div>
    </body>
</html>

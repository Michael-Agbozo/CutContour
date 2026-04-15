@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('partials.head')
    </head>
    <body class="h-full overflow-hidden bg-white antialiased">
        {{ $slot }}
        @fluxScripts
    </body>
</html>

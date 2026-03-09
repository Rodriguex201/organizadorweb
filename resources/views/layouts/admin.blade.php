<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'OrganizadorWeb')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="min-h-screen md:flex">
    <aside class="w-full border-b border-slate-200 bg-slate-900 text-slate-100 md:min-h-screen md:w-64 md:border-b-0 md:border-r">
        <div class="px-5 py-4 text-lg font-semibold">OrganizadorWeb</div>
        <nav class="space-y-1 px-3 pb-4">
            <a href="{{ url('/') }}" class="block rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->is('/') ? 'bg-slate-800' : '' }}">Inicio</a>
            <a href="{{ route('cobros.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('cobros.*') ? 'bg-slate-800' : '' }}">Cobros</a>
            <a href="{{ route('proformas.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('proformas.index', 'proformas.show') ? 'bg-slate-800' : '' }}">Proformas</a>
            <a href="{{ route('proformas.dashboard') }}" class="block rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('proformas.dashboard') ? 'bg-slate-800' : '' }}">Dashboard</a>
            <a href="{{ route('configuracion.estados-proforma.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('configuracion.estados-proforma.*') ? 'bg-slate-800' : '' }}">Configuración</a>
        </nav>
    </aside>

    <main class="flex-1 p-4 md:p-8">
        @yield('content')
    </main>
</div>
</body>
</html>

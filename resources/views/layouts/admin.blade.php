<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'OrganizadorWeb')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div id="admin-layout" class="min-h-screen md:flex">
    <aside id="admin-sidebar" class="w-full border-b border-slate-200 bg-slate-900 text-slate-100 transition-all duration-300 md:min-h-screen md:w-64 md:border-b-0 md:border-r">
        <div class="flex items-center justify-between px-5 py-4">
            <span id="sidebar-brand" class="text-lg font-semibold transition-all duration-300">OrganizadorWeb</span>
        </div>

        <nav class="space-y-1 px-3 pb-4">
            <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->is('/') ? 'bg-slate-800' : '' }}">
                <span class="text-base">🏠</span>
                <span class="sidebar-label">Inicio</span>
            </a>
            <a href="{{ route('cobros.index') }}" class="group flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('cobros.*') ? 'bg-slate-800' : '' }}">
                <span class="text-base">💰</span>
                <span class="sidebar-label">Cobros</span>
            </a>
            <a href="{{ route('proformas.index') }}" class="group flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('proformas.index', 'proformas.show') ? 'bg-slate-800' : '' }}">
                <span class="text-base">📄</span>
                <span class="sidebar-label">Proformas</span>
            </a>
            <a href="{{ route('proformas.dashboard') }}" class="group flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('proformas.dashboard') ? 'bg-slate-800' : '' }}">
                <span class="text-base">📊</span>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="{{ route('configuracion.estados-proforma.index') }}" class="group flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('configuracion.estados-proforma.*') ? 'bg-slate-800' : '' }}">
                <span class="text-base">⚙️</span>
                <span class="sidebar-label">Configuración</span>
            </a>
        </nav>
    </aside>

    <main class="flex-1 p-4 md:p-8">
        <header class="mb-4 flex items-center gap-3">
            <button id="sidebar-toggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded bg-slate-900 text-xl text-white transition-all duration-300 hover:bg-slate-800" aria-label="Colapsar o expandir menú lateral" aria-expanded="true">
                ☰
            </button>
            <h1 class="text-sm font-medium text-slate-500">Panel administrativo</h1>
        </header>

        @yield('content')
    </main>
</div>

<script>
    (() => {
        const layout = document.getElementById('admin-layout');
        const sidebar = document.getElementById('admin-sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        const brand = document.getElementById('sidebar-brand');

        if (!layout || !sidebar || !toggle) return;

        const applyCollapsedState = (collapsed) => {
            sidebar.classList.toggle('md:w-64', !collapsed);
            sidebar.classList.toggle('md:w-20', collapsed);

            document.querySelectorAll('.sidebar-label').forEach((el) => {
                el.classList.toggle('hidden', collapsed);
            });

            if (brand) {
                brand.classList.toggle('hidden', collapsed);
            }

            sidebar.querySelectorAll('a').forEach((link) => {
                link.classList.toggle('justify-center', collapsed);
                link.classList.toggle('justify-start', !collapsed);
            });

            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        };

        let collapsed = false;
        toggle.addEventListener('click', () => {
            collapsed = !collapsed;
            applyCollapsedState(collapsed);
        });

        applyCollapsedState(false);
    })();
</script>
</body>
</html>

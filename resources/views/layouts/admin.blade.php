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
    <aside id="admin-sidebar" class="h-screen w-full border-b border-slate-200 bg-slate-900 text-slate-100 transition-all duration-300 md:w-64 md:border-b-0 md:border-r">
        <div class="flex h-full flex-col">
            <div class="flex-1 overflow-y-auto">
                <div class="flex items-center justify-between px-5 py-4">
                    <span id="sidebar-brand" class="text-lg font-semibold transition-all duration-300">OrganizadorWeb</span>
                </div>

                <nav class="space-y-1 px-3 pb-4">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('clientes.*') || request()->is('/') ? 'bg-slate-800' : '' }}">
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
                    
                    @if(strtolower(trim(session('rol_nombre'))) === 'admin')
                        <div class="rounded px-3 py-2 {{ request()->routeIs('configuracion.*') ? 'bg-slate-800' : '' }}">
                            <div class="flex items-center gap-3 text-sm font-medium">
                                <span class="text-base">⚙️</span>
                                <span class="sidebar-label">Configuración</span>
                            </div>
                            <div class="mt-2 space-y-1 pl-7 sidebar-label">
                                <a href="{{ route('configuracion.directorio.index') }}" class="block rounded px-2 py-1 text-xs hover:bg-slate-700 {{ request()->routeIs('configuracion.directorio.*') ? 'bg-slate-700' : '' }}">Directorio</a>
                                <a href="{{ route('configuracion.estados-proforma.index') }}" class="block rounded px-2 py-1 text-xs hover:bg-slate-700 {{ request()->routeIs('configuracion.estados-proforma.*') ? 'bg-slate-700' : '' }}">Estados proforma</a>
                            </div>
                        </div>
                    @endif
                </nav>
            </div>

            <div class="mt-auto border-t border-slate-800 px-3 py-4">
                <div class="mb-3 space-y-1 text-xs text-slate-400 sidebar-label">
                    <p class="truncate">{{ session('usuario', 'usuario') }}</p>
                    <p class="uppercase tracking-wide">
                        Rol: {{ session('rol_nombre', session('rol', 'sin rol')) }}
                    </p>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        <span class="text-base">🚪</span>
                        <span class="sidebar-label">Cerrar sesión</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="flex-1 p-4 md:p-8">

        <header class="mb-4 flex items-center gap-3">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded bg-slate-900 text-xl text-white transition-all duration-300 hover:bg-slate-800" aria-label="Colapsar o expandir menú lateral" aria-expanded="true">
                    ☰
                </button>
                <h1 class="text-sm font-medium text-slate-500">Panel administrativo</h1>
            </div>
        </header>

        @yield('content')
    </main>
</div>

<script>
    (() => {

        const SIDEBAR_COLLAPSED_KEY = 'sidebarCollapsed';

        const sidebar = document.getElementById('admin-sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        const brand = document.getElementById('sidebar-brand');


        if (!sidebar || !toggle) return;


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


        const getStoredCollapsedState = () => {
            return localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true';
        };

        const setStoredCollapsedState = (collapsed) => {
            localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? 'true' : 'false');
        };

        let collapsed = getStoredCollapsedState();
        applyCollapsedState(collapsed);

        toggle.addEventListener('click', () => {
            collapsed = !collapsed;
            applyCollapsedState(collapsed);
            setStoredCollapsedState(collapsed);
        });
    })();
</script>

<script>
    window.addEventListener("pageshow", function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

@stack('scripts')
</body>
</html>

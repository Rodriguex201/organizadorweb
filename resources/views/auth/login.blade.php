<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesi&oacute;n</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-linear-to-br from-slate-100 via-indigo-50 to-fuchsia-100 text-slate-800">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-linear-to-b from-white/70 to-transparent"></div>
        <div class="pointer-events-none absolute -left-24 top-16 h-72 w-72 rounded-full bg-cyan-200/40 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 right-0 h-80 w-80 rounded-full bg-fuchsia-200/40 blur-3xl"></div>

        <main class="mx-auto flex min-h-screen w-full max-w-7xl items-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="grid w-full overflow-hidden rounded-[2rem] border border-white/60 bg-white/65 shadow-2xl shadow-slate-900/10 backdrop-blur xl:min-h-[720px] xl:grid-cols-[1.05fr_0.95fr]">
                <aside class="relative hidden overflow-hidden xl:flex">
                    <div class="absolute inset-0 bg-linear-to-br from-slate-950 via-indigo-900 to-fuchsia-800"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.18),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(255,255,255,0.12),_transparent_28%)]"></div>

                    <div class="relative flex w-full flex-col justify-between p-12 text-white">
                        <div class="space-y-8">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-white/20 bg-white/10 shadow-lg shadow-slate-950/20 backdrop-blur">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.75 6.75A2.75 2.75 0 0 1 7.5 4h9A2.75 2.75 0 0 1 19.25 6.75v10.5A2.75 2.75 0 0 1 16.5 20h-9a2.75 2.75 0 0 1-2.75-2.75V6.75Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 9.25h8M8 12h8m-8 2.75h4.5"/>
                                </svg>
                            </div>

                            <div class="max-w-xl space-y-5">
                                <div class="space-y-3">
                                    <p class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-200/90">Plataforma administrativa</p>
                                    <h1 class="text-5xl font-semibold tracking-tight text-white">OrganizadorWeb</h1>
                                </div>
                                <p class="max-w-lg text-lg leading-8 text-slate-200">
                                    Gesti&oacute;n de clientes, cobros y proformas.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-sm font-semibold text-cyan-100">&#10003;</span>
                                <span class="text-base font-medium text-white">Clientes</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-sm font-semibold text-cyan-100">&#10003;</span>
                                <span class="text-base font-medium text-white">Cobros</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-sm font-semibold text-cyan-100">&#10003;</span>
                                <span class="text-base font-medium text-white">Proformas</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-8 lg:px-12 xl:min-h-0 xl:px-16">
                    <section class="w-full max-w-md rounded-[2rem] border border-slate-200/80 bg-white p-8 shadow-[0_24px_80px_-28px_rgba(15,23,42,0.35)] sm:p-10">
                        <div class="mb-8 space-y-3">
                            <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-indigo-700">
                                Acceso seguro
                            </span>
                            <div class="space-y-2">
                                <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Iniciar sesi&oacute;n</h2>
                                <p class="text-sm leading-6 text-slate-500">
                                    Ingresa tus credenciales para acceder al sistema.
                                </p>
                            </div>
                        </div>

                        @if ($errors->any())
                            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50/90 p-4 text-sm text-red-700 shadow-sm">
                                <ul class="list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('login.attempt') }}" method="POST" class="space-y-5">
                            @csrf
                            <div>
                                <label for="usuario" class="mb-2 block text-sm font-semibold text-slate-700">Usuario</label>
                                <input
                                    id="usuario"
                                    name="usuario"
                                    type="text"
                                    value="{{ old('usuario') }}"
                                    required
                                    autocomplete="username"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-base text-slate-900 shadow-sm outline-none transition duration-200 placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                >
                            </div>

                            <div>
                                <label for="clave" class="mb-2 block text-sm font-semibold text-slate-700">Contrase&ntilde;a</label>
                                <input
                                    id="clave"
                                    name="clave"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-base text-slate-900 shadow-sm outline-none transition duration-200 placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                >
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-linear-to-r from-slate-950 via-indigo-700 to-fuchsia-700 px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-indigo-500/25 transition duration-200 hover:scale-[1.01] hover:shadow-xl hover:shadow-indigo-500/30 focus:outline-none focus:ring-4 focus:ring-indigo-200"
                            >
                                Ingresar
                            </button>
                        </form>
                    </section>
                </div>
            </section>
        </main>
    </div>
</body>
</html>

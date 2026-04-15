<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4">
        <section class="w-full rounded-lg bg-white p-6 shadow">
            <h1 class="mb-6 text-2xl font-semibold">Acceso al sistema</h1>

            @if ($errors->any())
                <div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="usuario" class="mb-1 block text-sm font-medium">Usuario</label>
                    <input
                        id="usuario"
                        name="usuario"
                        type="text"
                        value="{{ old('usuario') }}"
                        required
                        autocomplete="username"
                        class="w-full rounded border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none"
                    >
                </div>

                <div>
                    <label for="clave" class="mb-1 block text-sm font-medium">Contraseña</label>
                    <input
                        id="clave"
                        name="clave"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-800"
                >
                    Ingresar
                </button>
            </form>
        </section>
    </main>
</body>
</html>

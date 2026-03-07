<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle proforma #{{ $proformaId }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="rounded-lg bg-white p-6 shadow">
        <h1 class="text-xl font-bold">Detalle de proforma #{{ $proformaId }}</h1>
        <p class="mt-2 text-sm text-slate-600">Vista preparada para implementación futura del detalle administrativo.</p>
        <div class="mt-6">
            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Volver al listado</a>
        </div>
    </div>
</div>
</body>
</html>

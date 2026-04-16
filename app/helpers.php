<?php

if (!function_exists('esAdmin')) {
    function esAdmin(): bool
    {
        $rolNombre = session('rol_nombre', session('rol'));

        return is_string($rolNombre) && strtolower(trim($rolNombre)) === 'admin';
    }
}

if (!function_exists('esUsuario')) {
    function esUsuario(): bool
    {
        $rolNombre = session('rol_nombre', session('rol'));

        return is_string($rolNombre) && strtolower(trim($rolNombre)) === 'user';
    }
}

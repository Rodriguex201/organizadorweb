<?php

if (!function_exists('rolActual')) {
    function rolActual(): ?string
    {
        $rol = session('rol');

        if (!is_string($rol) || $rol === '') {
            return null;
        }

        return strtolower(trim($rol));
    }
}

if (!function_exists('tieneRol')) {
    function tieneRol(string|array $roles): bool
    {
        $rol = rolActual();

        if ($rol === null) {
            return false;
        }

        $rolesPermitidos = array_map(
            static fn (string $item): string => strtolower(trim($item)),
            is_array($roles) ? $roles : [$roles],
        );

        return in_array($rol, $rolesPermitidos, true);
    }
}

if (!function_exists('esAdmin')) {
    function esAdmin(): bool
    {
        return tieneRol('admin');
    }
}

if (!function_exists('esUsuario')) {
    function esUsuario(): bool
    {
        return tieneRol('user');
    }
}

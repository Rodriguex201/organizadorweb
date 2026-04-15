<?php

if (!function_exists('esAdmin')) {
    function esAdmin()
    {
        return session('rol_id') == 1;
    }
}

if (!function_exists('esUsuario')) {
    function esUsuario()
    {
        return session('rol_id') == 2;
    }
}

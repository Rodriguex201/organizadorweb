# Restricciones de base de datos

- Trabajar únicamente en archivos locales salvo autorización explícita del usuario.
- Antes de cualquier acción que pueda acceder a una base de datos, detenerse y pedir autorización.
- No ejecutar migraciones contra MySQL real ni SQL de escritura sobre `organizador`.
- No ejecutar `Schema::drop*`, `migrate:fresh`, `refresh`, seeders destructivos ni equivalentes contra la base real.
- Entregar cualquier cambio de estructura o datos como SQL para revisión y ejecución manual por el usuario.
- No ejecutar PHPUnit si existe cualquier posibilidad de utilizar MySQL.
- Los tests con base de datos solo pueden ejecutarse con SQLite `:memory:`, con una comprobación previa que aborte para cualquier otra conexión. La configuración cacheada de Laravel no es evidencia de aislamiento.
- Se permiten cambios locales en código, vistas, servicios, controladores y pruebas aisladas. La validación de sintaxis no debe arrancar Laravel.

## Página web

El usuario confirmó que ya creó manualmente `clientes_potenciales.vlrpaginaweb`
como `INT NULL DEFAULT 0` y el concepto `0103`, `SERVICIO PAGINA WEB`,
`cuenta = NULL`, `activo = 1`. No crear ni ejecutar una migración para estos datos.

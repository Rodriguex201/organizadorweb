# Retención en la fuente: catálogo y asignación

La sección Configuración → Tarifas administra `config_retenciones_fuente` mediante
`RetencionFuenteConfigService`, Query Builder y los permisos operativos existentes
`admin,user`. No hay endpoint de eliminación: el estado se cambia explícitamente
a activo o inactivo. Listado ascendente por porcentaje e ID.

Se admite un porcentaje entre 0 y 100, con hasta tres decimales, descripción
opcional de hasta 100 caracteres y estado booleano. La validación comprueba
duplicados entre activos e inactivos. El índice UNIQUE ya existente protege
también solicitudes concurrentes; su excepción se presenta como error de validación.
No se crean tablas, columnas, índices, claves foráneas ni migraciones.

Rutas nuevas, dentro del grupo `role:admin,user` de Tarifas:

- POST `/configuracion/tarifas/retenciones`
- PUT `/configuracion/tarifas/retenciones/{retencion}`
- PATCH `/configuracion/tarifas/retenciones/{retencion}/estado`

Crear y editar clientes comparten el selector de `basic-fields.blade.php`.
`No aplica` guarda NULL. El catálogo ofrece activos y, al editar, la retención
inactiva ya almacenada para ese cliente, identificada como tal. Ese ID procede
del registro leído por el servidor, nunca de un parámetro de selección del navegador.
La validación rechaza cualquier ID fuera de ese catálogo: inexistentes e inactivos
ajenos. Omitir el campo en una actualización conserva su valor anterior.

La retención solo se almacena como referencia. No participa en cálculo de
proformas, totales, PDF, pagos, activaciones ni portal. Los porcentajes se editan
en el catálogo; no existe en este cambio un historial ni cálculo fiscal.

## Pruebas aisladas

`tests/Isolated/retenciones_fuente.php` arranca sin el `.env` real ni caché de
configuración del proyecto. Antes de cargar Laravel exige que el único driver
PDO disponible sea SQLite y rechaza MySQLi. La fábrica de conexiones rechaza
cualquier destino diferente de SQLite `:memory:`. Las tablas de prueba se crean
solo en esa memoria, sin ejecutar migraciones. Las llamadas HTTP externas se bloquean.

Ejemplo (ajustar ruta de extensiones al PHP local):

```text
php -n -d extension_dir=<directorio-ext> -d extension=pdo_sqlite -d extension=mbstring -d extension=openssl -d extension=fileinfo tests/Isolated/retenciones_fuente.php
```

Cubre listado, orden, precisión, límites, duplicados y concurrencia, edición,
estado, permisos, creación y actualización real de clientes en SQLite,
selección inactiva, ID inexistente y renderizado de formularios.
No certifica conectividad ni ejecución en MySQL; cualquier prueba real posterior
requiere autorización y debe apuntar exclusivamente a `organizadorprueba`.

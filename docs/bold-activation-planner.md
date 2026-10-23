# Planificación durable de activaciones Bold

Esta fase solo planifica pagos ya aplicados. No ejecuta activaciones, consultas remotas,
correo, cobros ni creación de links. No vuelve a aplicar el pago ni modifica proformas.

## Configuración y alcance

`config/bold_activations.php` fija literalmente:

```php
'remote_enabled' => false,
'rules_version' => 'SIN_REGLAS_APROBADAS',
'rules' => [],
```

El resolver rechaza cualquier otra configuración antes de abrir la conexión.
No hay correspondencias implícitas entre códigos y servicios. Habilitar reglas
posteriormente requiere implementar y aprobar explícitamente su resolución.

La conexión dedicada usa exclusivamente `config('bold.database')` (`BOLD_DB_*`).
Exige entorno local, sandbox, credenciales completas, base `organizadorprueba`,
`SELECT DATABASE()` coincidente e InnoDB en las siete tablas usadas. No hay fallback
a la conexión administrativa. Las mutaciones del repositorio se limitan a
`organizadorprueba.activaciones_pago`; las demás tablas solo se leen/bloquean.

## Ejecución manual

```sh
php artisan bold:planificar-activaciones --id=<intento>
php artisan bold:planificar-activaciones --limit=10
```

El lote tiene un máximo de 50 intentos por invocación. No se añade scheduler,
webhook ni llamada automática desde la aplicación del pago. La siguiente ejecución
recupera un pago aplicado que todavía no tenga tareas.

## Transacción e idempotencia

1. Seleccionar únicamente intentos Bold sandbox con `estado_aplicacion=APLICADO`.
2. Bloquear el intento. Si ya existen tareas, devolver `YA_PLANIFICADO`, sin cambios.
3. Leer y bloquear proforma, cliente, cobro exacto y detalles. No hay fallback histórico.
4. Comprobar confirmación `PAGADO/PAID`, evidencia de aplicación, cliente y estado coherentes.
5. Conservar cada línea y su `ref_codigo`, incluidos duplicados, en `conceptos_origen`.
6. Insertar una única tarea `PLANIFICACION/REVISION`, con versión congelada.
7. Confirmar la transacción. Un fallo devuelve `REINTENTAR`; la próxima llamada
   consulta primero lo persistido, también si el resultado del commit fue incierto.

La clave SHA-256 estable depende de pasarela, ambiente, intento y servicio;
no cambia con una versión de reglas. Los índices únicos del DDL aprobado
protegen clave e intento/servicio. La transacción serializa planificadores del mismo pago.

Motivos: `REGLA_NO_APROBADA`, `CONCEPTO_DESCONOCIDO`, `CONCEPTO_SIN_CODIGO`,
`CLIENTE_SIN_CODIGO`, `PROFORMA_SIN_CONCEPTOS` o discrepancias de contexto.
Cada concepto conserva su motivo individual aunque la tarea tenga un motivo principal.
Sin reglas no se inventan fechas, conexión ni base destino: quedan NULL.
Una tarea en revisión no se replantea automáticamente al cambiar el catálogo o el cliente.

## Reservas y adaptadores deshabilitados

`reserve()` es una primitiva local de persistencia, sin ejecutor conectado al comando.
Solo admite tareas de servicio real en `PENDIENTE/REINTENTABLE`, objetivo presente
y vencimiento de backoff cumplido. El DDL exige los demás campos del objetivo.
El índice único empresa/servicio mantiene exclusión para `EJECUTANDO`, `VERIFICANDO`
e `INDETERMINADA`; la reserva de otro pago devuelve `OCUPADA`.
No se liberan reservas automáticamente por vencimiento: un resultado incierto
debe verificarse antes de permitir otra ejecución.

Los adaptadores normal y eventos rechazan siempre consultar, comparar, escribir
y releer. No crean PDO ni clientes HTTP, no resuelven conexiones administrativas
y no acceden a servidores de licencias. Las tareas de planificación en revisión
no son reservables. No existe todavía ejecución remota.

## Pruebas

`tests/Isolated/bold_activation_planner.php` no arranca Laravel. Ejecutar PHP sin INI,
cargando únicamente PDO SQLite (ajustar `extension_dir` al equipo):

```sh
php -n -d extension_dir=<directorio-ext> -d extension=pdo_sqlite tests/Isolated/bold_activation_planner.php
```

El test aborta si hay otros drivers PDO o mysqli; solo permite SQLite `:memory:`.
Cubre conceptos, pago aplicado/no aplicado, repetición sin cambios, recuperación
por lote, rollback antes de commit, reserva exclusiva y rechazo de operaciones remotas.

Validación adicional autorizada con MySQL/InnoDB: fixtures nuevos coherentes,
comando Artisan real restringido a sus IDs, comparación íntegra antes/después
de repetir, fallo inyectado antes de commit y dos procesos/conexiones concurrentes
reservando la misma empresa/servicio. Resultado: una reserva y una ocupación.
Los fixtures se eliminaron en orden de dependencias; las huellas de las ocho tablas
comparadas volvieron al valor inicial. No se reinició AUTO_INCREMENT.
Scripts, manifiestos y evidencias locales de esta validación no forman parte del repositorio.

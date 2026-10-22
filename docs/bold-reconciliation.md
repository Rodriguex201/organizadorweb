# Conciliación GET local/sandbox

`php artisan bold:conciliar --id=42` concilia un intento conocido.
`php artisan bold:conciliar --limit=5` procesa un lote, máximo 20 candidatos/GET
por ejecución. Ambos respetan el backoff: no existe force ni POST de creación.
No hay programación automática ni endpoint público. La operación manual inicial
es CLI; un botón administrativo futuro deberá usar el mismo servicio con permisos
y CSRF, sin aceptar URLs ni payment_link del navegador.

Requiere APP_ENV local, BOLD_ENV sandbox, BOLD_ENABLED true y BOLD_DB_* dedicados
con database exactamente organizadorprueba. No hay fallback DB_*. El repositorio
verifica SELECT DATABASE(). Solo se actualiza intentos_pago; no se consulta ni
actualiza sg_proform, ni se invoca correo, activaciones u OTP.

Se consultan ACTIVO, PROCESANDO, INDETERMINADO y LINK_CREADO con payment_link
persistido. Sin link no se hace GET. PAGADO, REVISION y cerrados se omiten.
Endpoint fijo: https://integrations.api.bold.co/online/link/v1/{payment_link}.
Reutiliza transporte con TLS, 10 s de conexión, 25 s total, máximo 64 KiB, sin
redirecciones ni reintentos. Una ejecución devuelve solo IDs locales, estados,
códigos internos y HTTP; no devuelve link, URL, credenciales ni cuerpo remoto.

Valida id, reference, total decimal exacto, CLOSE, is_sandbox booleano y estado.
No usa floats para dinero. El GET oficial no documenta moneda; la salida indica
moneda_verificada_get=false. Si llegara currency explícito y distinto, REVISION.
PAGADO significa únicamente confirmación remota en sandbox, no pago aplicado.
Para PAID se exige transaction_id no vacío; si contradice uno existente, REVISION.

ACTIVE -> ACTIVO; PROCESSING -> PROCESANDO; PAID -> PAGADO;
REJECTED -> PROCESANDO con REMOTE_REJECTED_LINK_RECHECK (bloqueante);
CANCELLED -> CANCELADO; EXPIRED -> EXPIRADO. Discrepancia -> REVISION.
PAGADO nunca vuelve a consultarse/degradarse por este servicio. Cancelados y
expirados liberan el bloqueo según el esquema existente. No modifica importes,
referencia, proforma, cliente, ambiente, moneda ni link.

Timeout/408/429/5xx preservan el estado y bloqueo, registran GET_UNAVAILABLE y
ultima_consulta_at. HTTP 404 -> REVISION con GET_HTTP_404. Otro HTTP no satisfactorio, JSON inválido o
contrato inválido -> REVISION. estado_remoto conserva el último estado válido,
que NO debe presentarse como una observación nueva si la última consulta falló.

Revisión documental del 404 (22/10/2026): la documentación oficial de
[API Links](https://developers.bold.co/pagos-en-linea/api-link-de-pagos)
describe GET /online/link/v1/{payment_link} y los estados, pero no define causas
ni transiciones para HTTP 404. No permite distinguir inexistencia, eliminación,
expiración u otra causa. EXPIRED se obtiene del status de una respuesta válida,
no del HTTP 404. REVISION es nuestra política conservadora, no un estado impuesto
por Bold: conserva el bloqueo, snapshot y último estado remoto; registra HTTP y
fecha, no reintenta automáticamente ni crea reemplazos. Un cuerpo de error 404
no se interpreta como estado de pago. Un PAGADO local no se consulta ni degrada.
La documentación de API de pagos en línea menciona PaymentNotFound para otros
endpoints; no se extrapola ese contrato a API Links. El intento 35 conserva su
evidencia anterior sin reescritura ni nueva consulta.

Validación final aislada: incluye 404 con cuerpo que aparenta EXPIRED, preservación
del snapshot/transaction_id/estado remoto, timestamp, exclusión de REVISION del
polling y no degradación de PAGADO. No requiere MySQL, red ni webhook. La entrega
real de webhook sandbox queda fuera de las pruebas; receptor y evento 8 se conservan.

Backoff durable sin columnas nuevas, según antigüedad del intento:
menos de 5 min: 60 s; menos de 30 min: 5 min; menos de 2 h: 15 min; después: 1 h.
ACTIVO con remoto ACTIVE: mínimo 30 min. La consulta manual respeta el intervalo.
No hay jitter ni Retry-After en esta primera versión; el reintento es de una
ejecución futura, nunca un bucle HTTP. El lote ordena por última consulta/creación.

GET_LOCK por ID (sin espera) serializa ejecutores de conciliación en MySQL.
El lock se libera en finally o al cerrar conexión. No mantiene transacción de fila
durante HTTP. El UPDATE usa estado, updated_at, ultima_consulta_at y snapshot
inmutable como compare-and-swap: descarta respuestas frente a cambios concurrentes.
El webhook sigue siendo independiente: almacena evidencia, no dispara GET todavía.
No se implementa aplicación comercial ni deduplicación de esa futura capa.

Prueba aislada: PHP -n con solo pdo_sqlite, tests/Isolated/bold_reconciliation.php.
SQLite en memoria obligatorio, sin Laravel/.env/MySQL/cURL. El doble HTTP nunca
permite POST. Las carreras se modelan alterando el estado durante el GET simulado;
esto no reemplaza una prueba futura con dos procesos MySQL reales.

Validación real acotada 22/10/2026: un GET por intento 42 y 35, sin POST.
42: 200 PROCESSING -> PROCESANDO; repetición inmediata omitida.
35: 404 -> REVISION; conservó PROCESSING como último estado válido anterior.
Huellas de las proformas relacionadas idénticas antes/después. Sin cambios a
eventos. PAID real todavía no observado: validado solo mediante respuesta simulada.

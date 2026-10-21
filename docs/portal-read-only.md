# Detalle de proforma: revisión local READ ONLY

Actualización OTP: el detalle directo con NIT+número ya no está habilitado.
Consultar `portal-otp-piloto.md` para el flujo vigente. La validación READ ONLY
de este documento describe la etapa anterior; ahora el correo se lee mediante
una quinta consulta fija y el detalle exige autorización OTP por proforma.

Implementación del 20 de octubre de 2026, validada el 21 de octubre contra
`organizadorprueba` con autorización expresa. Sin escrituras de datos,
migraciones ni commit.

## Entrada y alcance

`GET /portal/revision` presenta la consulta; `POST /portal/revision` muestra el
detalle o una respuesta genérica. Se usa POST para no colocar NIT y número en
la URL, historial ni logs de acceso habituales. No es una operación de escritura.

La ruta exige simultáneamente:

- `APP_ENV=local`.
- `PORTAL_REVIEW_ENABLED=true` (por defecto deshabilitada).
- Dirección del par de red `127.0.0.1` o `::1`, sin confiar en X-Forwarded-For.
- Host `localhost`, `127.0.0.1` o `[::1]`.
- En POST, `Origin` idéntico al origen del servidor.

No utiliza sesión, autenticación ni middleware administrativos. La protección
limita la revisión a un equipo local confiable; NIT y número **no son identidad
autenticada de un cliente**. No habilitar esta versión para acceso público ni
colocarla detrás de un proxy que convierta tráfico externo en loopback.
La futura autenticación pública queda fuera de este cambio.

Las rutas de maqueta permanecen con datos ficticios. El flujo real está separado
para no atribuir resultados de pago o activación simulados a documentos reales.
No se modificó `routes/web.php`.

## Consultas exactas y reglas

Son las cuatro constantes de `ProformaReadService::QUERIES`. Los valores se
envían mediante parámetros preparados nativos:

```sql
SELECT idclientes_potenciales
FROM clientes_potenciales
WHERE BINARY TRIM(nit) = BINARY ?
LIMIT 2
```

Debe existir exactamente un cliente. La comparación no elimina dígitos,
puntuación ni ceros iniciales. La entrada solo acepta 5–15 dígitos.

```sql
SELECT id, id_cobro, nro_prof, emp, mes, anio, vtotal, estado
FROM sg_proform
WHERE BINARY TRIM(nit) = BINARY ? AND BINARY nro_prof = BINARY ?
LIMIT 2
```

Debe existir exactamente una proforma para el documento y número exactos
(número: 1–50 letras, dígitos o guiones). IDs positivos canónicos obligatorios.
Un esquema sin `id_cobro` falla cerrado; no se modifica el esquema.

```sql
SELECT DISTINCT id_cliente
FROM valores_externos
WHERE id_cobro = ?
LIMIT 2
```

Debe existir exactamente una referencia distinta, positiva, canónica e idéntica
al ID del único cliente. Varias filas con la misma referencia exacta no producen
ambigüedad; referencias diferentes, nulas o mal formadas bloquean. No se usa
`MAX`, `LIMIT 1` ni fallback histórico por período. No se intenta corregir datos.

```sql
SELECT descripcion, cantidad, vr_unidad, vr_parcial
FROM sg_proford
WHERE proforma_id = ?
ORDER BY orden
LIMIT 501
```

Solo se ejecuta después de comprobar pertenencia. Se rechazan detalles vacíos
o con más de 500 líneas; nunca se muestra silenciosamente un detalle truncado.
Total y subtotales provienen del documento guardado, sin recalcular tarifas.
Los decimales se conservan como cadenas y se formatean sin convertir a float.

Cero coincidencias, múltiples coincidencias, relación ambigua, datos inválidos
o error de infraestructura devuelven el mismo mensaje, estructura y estado 200:
“No fue posible mostrar la proforma con los datos proporcionados.”
Esto unifica el contenido, pero no promete tiempos de respuesta constantes.

## Garantías READ ONLY y límites verificados

`MySqlReviewReader` crea un PDO independiente. No usa `DB`, Eloquent ni la
conexión predeterminada de Laravel. Antes de leer ejecuta:

```sql
SELECT DATABASE()
SET TRANSACTION ISOLATION LEVEL REPEATABLE READ
START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY
```

Son controles de la transacción, sin cambios de datos. Las cuatro lecturas
ocurren en esa misma conexión/transacción. Al terminar se hace ROLLBACK;
no existe COMMIT. Si el servidor rechaza READ ONLY, se bloquea la consulta.
La lectura coherente requiere tablas transaccionales compatibles (InnoDB);
esto todavía no se verificó en la base real.

El lector solo acepta exactamente las cuatro consultas de la lista permitida.
No acepta SQL del usuario, procedimientos, bloqueo `FOR UPDATE` ni múltiples
sentencias. No contiene INSERT, UPDATE o DELETE.

Por decisión expresa del usuario se utiliza `RmSoft20X` en el PDO dedicado.
La cuenta tiene permisos amplios: la protección READ ONLY corresponde a este
lector y su transacción, no a los privilegios MySQL del usuario. No se cambian
cuentas ni permisos. La contraseña se configuró explícitamente en la variable
dedicada, sin fallback a DB_PASSWORD ni a otra conexión.
El lector rechaza cualquier destino distinto de 168.232.32.74:3306,
base organizadorprueba y usuario RmSoft20X antes de conectar. La primera
sentencia verifica DATABASE() exactamente; cualquier error bloquea el detalle.

No se invoca el servicio de PDF, que puede escribir archivos y actualizar
`rpdf/npdf/hpdf`; tampoco `updateEstado`, métodos de envío/activación,
`syncDefaults`, `updateColors` o `contextoComprobante` (usa `lockForUpdate`).
Del servicio existente se reutilizan únicamente las constantes `MESES` y
`ESTADOS`. El patrón de lectura de `sg_proford` procede del generador PDF,
sin invocar su flujo. No se reutiliza el lookup administrativo que reduce
relaciones ambiguas mediante `MAX(id_cliente)`.

## Datos visibles

Solo número, empresa, período, conceptos, cantidades, unitarios, subtotales,
total y estado. El DTO excluye NIT, IDs, correo, notas, rutas de archivos y
comprobantes. No hay botones de pago ni enlaces de activación. Los valores se
escapan como HTML. No se registran excepciones con SQL ni identificadores.
La respuesta usa `no-store`, `noindex`, protección contra frames y CSP.

## Configuración de la revisión local

Variables dedicadas configuradas en `.env`, fuera de Git. APP_ENV=local se
aplica solamente al proceso de revisión; el valor principal sigue production:

```dotenv
APP_ENV=local
PORTAL_REVIEW_ENABLED=true
PORTAL_READ_HOST=168.232.32.74
PORTAL_READ_PORT=3306
PORTAL_READ_DATABASE=organizadorprueba
PORTAL_READ_USERNAME=RmSoft20X
PORTAL_READ_PASSWORD=<secreto_local>
```

Mantener el servidor ligado a loopback. El proceso debe cargar esta
configuración; una caché anterior podría impedir que tome los valores nuevos.
No se ejecutaron comandos Artisan ni se modificó la caché administrativa.
Solo se añadieron variables PORTAL_* a `.env`; las variables DB_* y APP_ENV
permanecen intactas. El proceso local usa una caché independiente.
El PHP de la ejecución real deberá disponer de PDO MySQL. No usar el harness
aislado para datos reales: deliberadamente elimina credenciales y drivers.

## Pruebas ejecutadas

44 comprobaciones mediante
`tests/Isolated/portal_laravel_validation.php --review-tests`.
Arranque previo con `php -n`, sin ningún driver PDO ni mysqli, fábrica de
conexiones Laravel bloqueada, entorno y caché aislados.

- Servicio con lector simulado: coincidencia única, cero/múltiples registros,
  cliente ajeno, IDs inválidos, ausencia de enlace, límites, estado desconocido,
  decimales y parámetros.
- Vista real de Laravel: escape HTML, precisión y lista de campos públicos.
- Kernel/rutas reales: bloqueo por producción, configuración deshabilitada,
  IP externa, host externo, X-Forwarded-For, Origin ajeno o ausente; formulario
  local, no cookies, no-store y respuesta genérica.
- Navegador local sin BD: formulario y envío con datos ficticios, respuesta
  genérica al faltar conexión. Se corrigió Referrer-Policy a `same-origin`
  porque `no-referrer` impedía validar Origin en el envío normal del navegador.
- Sintaxis PHP y `git diff --check`.

Las pruebas aisladas no crearon tablas ni insertaron fixtures: usan arrays.
Además se efectuó una validación real autorizada, exclusivamente de lectura:
DATABASE()=organizadorprueba, CURRENT_USER()=RmSoft20X@%, proforma 5521,
Octubre de 2026, Generada, una línea, total 45000.00. El lector validó la
pertenencia sin ambigüedad y POST /portal/revision devolvió HTTP 200 con el
detalle real. No se accedió a datos de otras bases. Para elegir el caso se
leyeron únicamente nit y nro_prof de hasta 20 cabeceras recientes mediante
un script diagnóstico externo al portal, en la misma transacción READ ONLY;
esa consulta no se añadió a la lista permitida del lector.
La revisión está servida en 127.0.0.1:8791 con router temporal fuera del proyecto,
conexiones Laravel ordinarias bloqueadas y rutas ajenas al portal bloqueadas.

### Validación negativa autorizada (21 de octubre de 2026)

Se verificó DATABASE()=organizadorprueba antes y después, usando READ ONLY.
El control positivo mostró la proforma 5521. Se eligió otra proforma real
cuya pertenencia a otro cliente también fue validada, sin imprimir su NIT.
Los tres casos negativos por HTTP fueron: NIT inexistente con 5521; NIT del
cliente de 5521 con proforma ajena; NIT del otro cliente con 5521. Todos
respondieron HTTP 200 con el mismo cuerpo byte por byte, mensaje genérico,
sin tarjeta de detalle ni conceptos, y Cache-Control no-store.
Host externo devolvió 404 en el servidor activo. Las 44 pruebas aisladas
confirmaron 404 para GET y POST en production y desde IP no loopback,
403 para POST sin Origin o con Origin externo, y rechazo de X-Forwarded-For
como forma de eludir el control de IP. El socket real escucha exclusivamente
en 127.0.0.1:8791. No se realizó una conexión desde otro equipo; entorno e IP
externos se simularon sobre el kernel y middleware reales sin drivers de BD.
Estas comprobaciones no prueban igualdad de tiempos de respuesta.

## Archivos

- `config/portal_review.php`
- `app/Portal/ReadOnly/{SelectReader,MySqlReviewReader,ProformaReadService}.php`
- `app/Http/Middleware/LocalPortalReview.php`
- `app/Http/Controllers/Portal/ReadOnlyProformaController.php`
- `routes/portal.php`
- `resources/views/portal/revision.blade.php`
- `tests/Isolated/portal_laravel_validation.php`
- `tests/Isolated/portal_read_only_checks.php`
- Este documento.

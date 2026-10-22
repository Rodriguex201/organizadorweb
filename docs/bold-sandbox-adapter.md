# Bold: preparación local, sin transporte

## Alcance entregado

La creación y consulta reales de un link sandbox se validaron mediante un ejecutor
temporal local, externo al repositorio, con un POST y un GET sin reintentos. Se verificó
el ambiente remoto y la reutilización del intento, sin completar pagos. Ese ejecutor,
sus respuestas, referencias reales y URLs no forman parte de esta entrega.

Este repositorio incluye configuración, autorización, reserva, persistencia,
validación de creación y verificación de respuestas de consulta. No contiene todavía
un transporte POST/GET operativo: `createLink()` continúa bloqueado incluso con
BOLD_ENABLED=true. La prueba externa no equivale a habilitar pagos en la aplicación.

Revisión documental: 2026-10-21. Fuentes oficiales:
- https://developers.bold.co/pagos-en-linea/api-link-de-pagos
- https://www.developers.bold.co/pagos-en-linea/llaves-de-integracion
- https://www.developers.bold.co/pagos-en-linea/boton-de-pagos/ambiente-pruebas

## Configuración privada

El operador configura únicamente en `.env` local:
```ini
BOLD_ENABLED=false
BOLD_ENV=sandbox
BOLD_API_KEY=
BOLD_DB_HOST=
BOLD_DB_PORT=3306
BOLD_DB_DATABASE=organizadorprueba
BOLD_DB_USERNAME=
BOLD_DB_PASSWORD=
```
La llave de identidad corresponde a Botón de pagos / API Link, ambiente pruebas.
El endpoint de creación no utiliza `BOLD_SECRET_KEY`; no se carga ni necesita en esta fase.
No copiar credenciales en documentación, pruebas o registros. No se cambió `.env`.

## Contrato propuesto

POST `https://integrations.api.bold.co/online/link/v1`

Headers futuros: `Authorization: x-api-key <llave privada configurada>`,
`Content-Type: application/json`, `Accept: application/json`.

Cuerpo ilustrativo (importe sintético; se sustituye exclusivamente por `sg_proform.vtotal`):
```json
{"amount_type":"CLOSE","amount":{"currency":"COP","total_amount":100.00,"tip_amount":0},"reference":"PF_<32 caracteres hexadecimales aleatorios>"}
```
`prepare()` produce el JSON exacto con número decimal, sin convertir dinero a float.
No se envían correo, NIT, nombre, impuestos inventados ni callback local.
Referencia: `PF_` + 16 bytes criptográficamente aleatorios en hexadecimal (35 caracteres).
Es un borrador en memoria: no es una intención persistida ni un link creado.

## Autorización y aislamiento

Construir el servicio con la instancia `PortalOtpService` del portal, `PortalReadGateway`,
`config('bold')` y `app()->environment()`. No hay ruta ni botón conectado en esta fase.
Único argumento de preparación: token opaco OTP. No acepta monto ni proforma del navegador.
Se valida vigencia del grant y se resuelve nuevamente cliente/proforma/relación inequívoca.
Reutiliza consultas fijas existentes; `MySqlReviewReader` solo permite organizadorprueba,
verifica DATABASE(), inicia READ ONLY y nunca usa la conexión administrativa.
Solo estados 2/3 son elegibles; pagada, facturada y desconocidos bloqueados.
El importe debe ser decimal positivo con máximo dos decimales; no se recalcula retención.

`createLink()` bloquea antes de leer datos cuando está deshabilitado. Incluso habilitado
rechaza el envío: el transporte real aún no está implementado. No hay logs ni captura de
headers, tokens, cuerpos o excepciones originales. No hay efectos administrativos.

## Respuesta y ambientes

El parser admite `payload` objeto, `payment_link` string no vacío y `url` HTTPS válida,
sin credenciales embebidas, con host exacto checkout.bold.co y puerto ausente o 443.
Exige respuesta 2xx y errors array vacío. La entrada de cuerpo comprueba JSON y su
Content-Type. No exige prefijo LNK_ ni igualdad textual entre URL e identificador.
Conserva límites locales de almacenamiento: ID 128 bytes y URL 2048 bytes.
Una creación válida pasa únicamente a LINK_CREADO, sin exponer el checkout.
Un link creado NO significa pago aprobado. No guardar respuestas completas.
ACTIVO requiere un GET oficial posterior y verificación de ID, referencia, importe
congelado, tipo CLOSE, estado ACTIVE e `is_sandbox=true`. El transporte sigue sin
conectarse en el repositorio; las pruebas entregan respuestas simuladas al verificador.
La creación no devuelve ese indicador; `BOLD_ENV` no prueba el ambiente de una llave.
Bold distingue el entorno mediante credenciales de pruebas/producción, no una URL sandbox
inventada. Producción está bloqueada en este adaptador. Confirmar límites de métodos de
pago antes del primer envío; no hardcodear límites comerciales a partir de ejemplos.

## Diseño de persistencia y duplicados

La persistencia durable de intentos ya está implementada: ID interno, referencia UNIQUE,
proforma_id, cliente_id, ambiente, moneda, monto decimal congelado, estado del intento,
fechas, payment_link UNIQUE, URL validada, estado remoto e is_sandbox verificado. Solo almacenar
código de error saneado y estado HTTP; jamás llaves, OTP, tokens o cuerpo completo.

Reservar atómicamente un único intento activo por proforma/ambiente, bajo bloqueo. Reutilizar
link vigente; rechazar cambios de monto o pago ya registrado. Ante timeout dejar estado
indeterminado y conciliar, sin repetir POST ni crear otra referencia automáticamente.
La documentación consultada no promete idempotencia por referencia ni un header de
idempotencia: no asumirlos. La referencia aleatoria sola NO evita dobles intentos.
Preparar varias veces hoy genera borradores independientes sin efecto externo.
La tabla se creó manualmente por el operador; el código no crea tablas ni ejecuta migraciones.

## Reserva y persistencia implementadas

`BoldAttemptService::forPortal($otp)` construye explícitamente el servicio con la instancia
OTP del portal. No se registra ninguna ruta de pago. `reserve($token)` valida la sesión,
propiedad, estado y vtotal mediante el lector READ ONLY existente. Después inserta un
intento en una conexión PDO separada, limitada por código a organizadorprueba y entorno
local/sandbox. Nunca utiliza la conexión administrativa predeterminada. Utiliza la
configuración privada `bold.database` mediante BOLD_DB_* sin cambiar .env.
No existe fallback a DB_* ni a credenciales de portal_review. Host, puerto, usuario
y contraseña deben configurarse expresamente; ausentes o inválidos bloquean antes
de abrir PDO. Solo se permite local/sandbox y database exactamente organizadorprueba.
El host admite nombre DNS o IPv4 y no admite separadores de DSN. Tras conectar se
comprueba SELECT DATABASE(). Las lecturas de proforma conservan su configuración
READ ONLY separada. No configurar estas variables deja la persistencia bloqueada.

El único INSERT y todos los UPDATE del repositorio apuntan a
`organizadorprueba.intentos_pago`; antes de operar se comprueba DATABASE(). No ofrece
ejecución SQL arbitraria ni borrado. Las consultas de proforma/cliente siguen por READ ONLY.
Esto es una restricción de código, no una reducción de los privilegios MySQL de la cuenta.

El INSERT es atómico y la clave uq_intento_bloqueante arbitra doble clic/concurrencia.
Ante duplicado se recupera el intento bloqueante. No se hace UPDATE de campos congelados.
`claim($token)` revalida por servidor y realiza un UPDATE condicionado a RESERVADO;
solo un ejecutor obtiene la fila. Los demás reciben null. No hay HTTP en este método.
Una discrepancia de importe/identidad lleva a REVISION sin alterar el snapshot.

`recordCreation()` recibe exclusivamente resultados internos del futuro ejecutor, nunca
parámetros del navegador: 2xx válido -> LINK_CREADO, timeout/408/429/5xx -> INDETERMINADO,
otra respuesta inválida -> REVISION. No persiste cuerpos ni mensajes remotos. Si guardar
el resultado falla, SOLICITANDO sigue bloqueando: no se reenvía automáticamente.
`recordVerification()` solo activa tras comprobar ID, referencia, total, CLOSE, ACTIVE
y sandbox remoto; una inconsistencia lleva a REVISION. Se omite checkout_url de resultados
no verificados. Ningún estado de intento actualiza la proforma o activa servicios.

FALLIDO solo se permite desde RESERVADO, antes de dispatch. EXPIRADO/CANCELADO liberan
la clave según el DDL, pero no se exponen transiciones automáticas hacia ellos: requieren
la conciliación futura. PAGADO y REVISION conservan el bloqueo. No existen reintentos POST.

La lectura de la proforma y la escritura del intento usan conexiones diferentes; no hay
un bloqueo compartido con las acciones administrativas. Antes del transporte futuro deberá
revalidarse nuevamente el estado/importe y coordinarse cualquier edición administrativa.
La protección implementada contra duplicados es sobre intentos, no un bloqueo de sg_proform.

Validación: `php -n -d extension=pdo_sqlite -d extension=openssl tests/Isolated/bold_attempts.php`
con extension_dir adecuado. Solo SQLite :memory:, guard previo a autoload, OTP simulado,
sin bootstrap Laravel ni .env. Fibers intercalan dos solicitudes antes de reservar/reclamar,
verificando colisión única y compare-and-swap. No sustituye una prueba paralela InnoDB real;
las pruebas aisladas no crean filas en MySQL. Las validaciones reales anteriores son
independientes de esta suite y sus evidencias no forman parte del repositorio.

## Pruebas

`php -n -d extension=openssl tests/Isolated/bold_payment.php` (ajustar extension_dir local).
Guard previo a autoload: cero drivers PDO, sin mysqli, sin bootstrap Laravel ni .env.
Usa OTP real con almacenamiento temporal y entrega simulada, lector SELECT simulado,
respuestas Bold simuladas y no dispone de transporte HTTP.

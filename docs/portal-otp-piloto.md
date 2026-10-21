# Piloto local OTP

Implementado el 21 de octubre de 2026; ampliado para selección de múltiples
correos. El piloto local fue aprobado tras un envío controlado aceptado por
Resend, recepción confirmada y acceso al detalle mediante OTP. El envío quedó
deshabilitado al terminar. Las direcciones, claves y evidencias de esa prueba
permanecen fuera del repositorio. El lector del portal no escribe en MySQL.

## Configuración privada

Configurar exclusivamente en `.env`, ignorado por Git:

```dotenv
PORTAL_OTP_SEND_ENABLED=false
PORTAL_OTP_KEY=<base64 de 32 bytes criptográficamente aleatorios>
PORTAL_OTP_ALLOWLIST="persona-autorizada@example.com"
```

Generar la clave localmente con `base64_encode(random_bytes(32))`. No usar el
texto del ejemplo, no publicarla ni enviarla por chat. No se reutiliza APP_KEY.
La allowlist permite separar direcciones por coma o punto y coma. Si el cliente
tiene varios correos válidos, se muestran exclusivamente máscaras y un ID opaco
aleatorio por opción. No se selecciona ni envía automáticamente a todos. Con
un solo correo se selecciona automáticamente. Un segmento malformado bloquea
conservadoramente la lista; sin correos válidos se conserva respuesta genérica.
Se recortan espacios, normaliza el dominio a minúsculas y eliminan duplicados;
la parte local conserva su caso para evitar fusionar buzones distintos.
El mapeo ID/correo solo existe dentro del estado privado cifrado, ligado a la
cookie HttpOnly del desafío, con 5 minutos de vigencia. La selección se consume
atómicamente. Antes de enviar se vuelve a resolver cliente/proforma y se exige
que el correo elegido siga en la lista actual, los IDs coincidan y la dirección
esté autorizada. Un campo email arbitrario del navegador no tiene efecto.
Un ID manipulado, de otro desafío, vencido o reutilizado no permite enviar.
El selector no indica qué correos están en la allowlist. Mostrar opciones
enmascaradas revela que existe una selección disponible; es un cambio explícito
respecto al flujo anterior de respuestas indistinguibles, limitado al piloto local.

Conservar `PORTAL_REVIEW_ENABLED=true` y las variables `PORTAL_READ_*`
dedicadas a organizadorprueba. APP_ENV=local se aplica únicamente al proceso
piloto, no al sistema administrativo. Ejecutar `php artisan config:clear` tras
cambiar la configuración local. Reiniciar el proceso con su caché independiente después
de configurar las variables privadas. No habilitar SEND_ENABLED hasta aprobar
el primer envío real y el destinatario de la allowlist.

El router temporal `portal-live-router.php` del proceso 8791 fue adaptado para
cargar estas variables privadas y permitir exclusivamente el endpoint de Resend
cuando el interruptor esté explícitamente habilitado. Mantiene bloqueadas las
conexiones DB ordinarias, correo administrativo, colas y rutas fuera del piloto.
Este helper vive en la carpeta local de evidencias, fuera del repositorio.

PortalOtpMailer usa RESEND_API_KEY y RESEND_FROM_ADDRESS ya existentes. No
usa MAIL_*, adjuntos, controladores administrativos ni actualizaciones de estado.
Su propio interruptor y la comprobación de entorno local bloquean el transporte
incluso si se invoca accidentalmente fuera del flujo. Sin configuración válida
o ante error se devuelve una respuesta pública genérica, sin registrar secretos.

## Rutas y autorización

GET /portal, GET /portal/proforma y GET/POST /portal/revision usan el mismo
controlador, middleware local y autorización OTP. Solo POST /portal/revision
solicita/verifica OTP. No se emplea StartSession administrativo, idusuario,
roles ni Auth::login. Las rutas administrativas no se modificaron.
Las rutas restantes de maqueta siguen siendo ficticias y no obtienen datos reales.

La solicitud muestra siempre el mensaje genérico y formulario de código,
sin datos del cliente. La resolución valida cliente/proforma/relación antes
de leer correo; todavía no lee conceptos. La única consulta nueva permitida es:

```sql
SELECT email FROM clientes_potenciales WHERE idclientes_potenciales = ? LIMIT 2
```

El parámetro es el ID exacto resuelto inequívocamente. Se conserva el PDO
dedicado, SELECT DATABASE() exacto, transacción READ ONLY, consultas fijas,
parámetros preparados y ausencia de fallback. No se actualizan proformas.

Cookie opaca de desafío: 5 minutos. Cookie opaca de autorización: 15 minutos
absolutos desde la verificación; ambas HttpOnly, SameSite=Strict, Path=/portal,
sin Domain, Secure cuando se usa HTTPS. HTTP se admite solo en loopback local.
POST requiere Origin idéntico; no acepta un origen ausente. El almacenamiento
conserva solo HMAC de los tokens. El cliente no decide los IDs autorizados.
Cada acceso recupera el permiso, comprueba expiración y vuelve a resolver
pertenencia, exigiendo los mismos cliente_id y proforma_id. Los parámetros
de cambio de destino bloquean el detalle. Nueva consulta revoca el permiso
anterior y requiere otro OTP. /portal/revision no conserva el bypass previo.

## Estado privado y límites

Directorio fijo: storage/app/private/portal-otp/, fuera de public y excluido de
Git. Verificar que las ACL del equipo permitan acceso únicamente a la cuenta
del proceso y administradores autorizados; chmod no sustituye ACL de Windows.
No publicar storage/app/private mediante alias del servidor web.

Un bloqueo estable state.lock serializa operaciones entre procesos. state.enc
contiene estado cifrado autenticado (AES-256-CBC con MAC de Encrypter). La
escritura usa un temporal cifrado y reemplazo bajo el mismo lock. Solo devuelve
un permiso después de persistirlo. Archivo corrupto, clave incorrecta o fallos
de escritura/bloqueo bloquean el flujo. Rotar la clave exige retirar el estado
previo de forma controlada; no se reinicia silenciosamente un estado ilegible.

OTP: 8 dígitos mediante random_int, HMAC ligado al desafío, 300 segundos,
5 fallos máximos y consumo atómico. Un reenvío aceptado invalida el anterior;
un envío fallido invalida su desafío. No se guarda el OTP en texto plano.
Los timestamps de límites y permisos vencidos se depuran bajo bloqueo.

Límites de ventana móvil: IP 5/min y 30/h; NIT+proforma 3/15min y 6/h;
destinatario 3/15min y 6/h; 60s entre solicitudes por NIT+proforma;
verificación 20/15min por IP, además de 5 fallos por desafío. Los intentos
contra desafíos inexistentes también cuentan. Claves HMAC, sin nombres de
archivo con NIT/correo. Los límites se conservan al reenviar y no se reinician
al consumir OTP. El flujo nunca usa el cache/session/queue de MySQL.

El envío síncrono fue autorizado para este piloto: aunque contenido y estado
públicos son genéricos, los tiempos de entrega pueden revelar diferencias.
No se afirma resistencia a enumeración temporal ni se habilita acceso público.

## Validación

Ejecutar tests/Isolated/portal_laravel_validation.php --review-tests con PHP -n,
sin drivers PDO/MySQLi y con mbstring, openssl y fileinfo. Requiere
PORTAL_VALIDATION_DIR privado y escribible. El harness bloquea la fábrica DB
de Laravel y solicitudes HTTP no interceptadas. No utilizar PHPUnit/MySQL.

117 comprobaciones aprobadas: pertenencia/ambigüedad, destinatarios, caducidad,
5 fallos, uso único, reenvío, todos los límites, fallos de almacenamiento,
payload Resend interceptado, interruptor de envío, cookies, HTTP sin bypass,
flujo HTTP exitoso con SQL simulado, autorización limitada, revocación y dos
procesos concurrentes con exactamente un consumo exitoso. No se imprimen OTPs.
La entrega real por Resend y la recepción por el destinatario fueron confirmadas
en la prueba controlada. La repetición manual del mismo código real no se completó;
el rechazo por reutilización está verificado por las pruebas aisladas.

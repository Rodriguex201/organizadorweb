# Receptor local de eventos Bold

Ruta: `POST /portal/pagos/bold/webhook`. Deshabilitada por defecto. No tiene sesión
administrativa, OTP ni middleware `web`. Las transformaciones globales de entrada
se omiten únicamente en esta ruta para no parsear JSON antes de verificar la firma.

Configuración privada local (no añadir secretos al repositorio):

```ini
BOLD_WEBHOOK_ENABLED=false
BOLD_WEBHOOK_MERCHANT_ID=
```

Utiliza exclusivamente `BOLD_DB_*`, sin fallback, con base obligatoria
`organizadorprueba`. Además exige APP_ENV local, BOLD_ENV sandbox, host local y
REMOTE_ADDR loopback; no confía en X-Forwarded-For. No se ha expuesto a Internet.
No habilitar proxies/túneles hacia la aplicación en esta fase.

## Contrato

Documentación oficial consultada: https://developers.bold.co/webhook y
https://developers.bold.co/pagos-en-linea/boton-de-pagos/ambiente-pruebas

`x-bold-signature` = HMAC-SHA256 hexadecimal de Base64(RAW), usando clave vacía
en sandbox. Comparación constante antes del parsing o acceso a BD. Esa clave
pública NO autentica al emisor: VALIDADO solo significa evidencia coherente de
pruebas, nunca permiso para aplicar pagos. No existe fallback de producción.

En API Links, data.metadata.reference se correlaciona con payment_link persistido,
no con la referencia PF interna. Se exige un único intento sandbox verificado,
importe decimal idéntico, moneda COP, comercio configurado, integration LINK,
source /payments/links y subject igual a data.payment_id. Si el intento ya tiene
transaction_id, también debe coincidir. No se exige patrón LNK.

El receptor acepta los cuatro eventos SALE_APPROVED, SALE_REJECTED, VOID_APPROVED
y VOID_REJECTED. Otros tipos quedan IGNORADO; discrepancias quedan REVISION.
Los importes se extraen como lexemas decimales, sin convertir floats a dinero.
Notación exponencial o más de dos decimales quedan REVISION, sin redondear.

## Persistencia y respuestas

Requiere la tabla eventos_pago creada manualmente. No hay migraciones. El único
INSERT es sobre organizadorprueba.eventos_pago. intentos_pago solo se consulta;
sg_proform no se consulta ni modifica. No se ejecutan pagos, correo, GET, POST
a Bold, webhooks salientes ni activaciones.

Se conserva una lista explícita de campos y SHA-256 del RAW, no el RAW, datos del
pagador, headers ni URLs. motivo es un código interno. No se registran excepciones.

- Firma inválida: 400 sin BD. JSON inválido: 400. Content-Type incorrecto: 415.
- Cuerpo mayor de 64 KiB: 413. El controlador lee como máximo 64 KiB + 1 byte.
- Nuevo evento persistido: 200. ID repetido con mismo hash: 200 sin insertar.
- Mismo ID con hash distinto: 409, preservando el evento original.
- La restricción UNIQUE resuelve también carreras entre comprobación e INSERT.
- Fallo de conexión/persistencia: 503, nunca 200 antes de almacenamiento durable.
- Configuración/entorno/origen no permitido: 404.

No hay trabajo externo en la petición. Timeout de conexión y espera de bloqueo
InnoDB de un segundo; el objetivo de respuesta es menos de dos segundos. La
latencia real deberá medirse antes de exponer el receptor: estos límites no
constituyen una garantía de duración total bajo fallos de red.

## Pruebas aisladas

Ejecutar PHP con `-n`, extension_dir explícito y únicamente `pdo_sqlite` (agregar
mbstring para pruebas HTTP de Laravel). Los scripts abortan si hay driver MySQL;
no cargan .env ni arrancan la aplicación real. Toda BD de pruebas es SQLite
en memoria. El test modela la unicidad, no sustituye validar InnoDB posteriormente.

`tests/Isolated/bold_webhook.php` verifica firma, idempotencia, conflicto, validación,
fallos y ausencia de cambios al intento. Ningún test realiza llamadas a Bold.

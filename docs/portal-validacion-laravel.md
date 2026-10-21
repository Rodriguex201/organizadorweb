# Validación del portal en Laravel y diagnóstico READ ONLY

Fecha: 20 de octubre de 2026. La maqueta visual aprobada se conserva.

## Entorno y alcance

Se arrancó **Laravel real** con su kernel HTTP, proveedores, colección de rutas,
controladores y motor de vistas mediante `tests/Isolated/portal_laravel_validation.php`.
Servidor local: http://127.0.0.1:8789/portal.
El servidor anterior en 8788 era una previsualización sin arranque de Laravel.

El arranque de validación:

- Ejecuta PHP con `-n`, sin PDO MySQL, PDO SQLite ni mysqli; aborta antes del
  autoload si encuentra un controlador de BD.
- No carga `.env` ni las cachés reales de configuración/rutas.
- Usa una conexión deliberadamente inválida y una fábrica de conexiones que
  registra y rechaza cualquier intento antes de conectarse.
- Aísla sesión y caché en memoria; vistas compiladas, logs y manifiestos en una
  carpeta externa de evidencias. Usa una clave exclusivamente de prueba.
- Bloquea resolución de correo y colas, y peticiones externas del cliente HTTP
  de Laravel. El código del mock no invoca servicios de pago o activación.
- No utiliza PHPUnit, Artisan, migraciones ni comandos sobre BD.

Esto valida la aplicación con dependencias de infraestructura aisladas, no
certifica el funcionamiento de la configuración productiva ni de procesos
administrativos que necesitan datos reales.

## Resultados

| Comprobación | Resultado |
| --- | --- |
| `/portal` | 200 |
| `/portal/verificacion` | 200 |
| `/portal/proforma` | 200 |
| `/portal/resultado/aprobado`, `/pendiente`, `/rechazado` | 200 en los tres escenarios |
| `/portal/activacion/pendiente`, `/completada`, `/incidencia` | 200 en los tres escenarios |
| Estado no permitido en resultado o activación | 404 |
| `/portal/admin` | 404 |
| `/clientes`, `/proformas`, `/cobros`, `/configuracion/usuarios` como visitante | 302 hacia `/login` |
| `/login` | 200 |
| `/portal-mock/portal.css` y `/portal-mock/portal.js` | 200, tipos CSS y JavaScript correctos |
| Cookies emitidas por las nueve pantallas del portal | Ninguna |
| Comparación de las 106 rutas de `routes/web.php` | Métodos, URI, nombre, acción, middleware y restricciones idénticos |
| Consola del navegador durante recorrido del portal | Sin errores ni advertencias registrados |
| Intentos de BD, correo o cola registrados por el bloqueo | Ninguno |

La comparación de rutas vuelve a registrar `routes/web.php` sin el portal en
una colección temporal y la compara con la colección completa. Además,
`routes/web.php` no tiene cambios en Git. No se inició sesión administrativa:
se comprobaron el registro de rutas y la protección de acceso como visitante,
no operaciones administrativas autenticadas.

## Comportamiento y responsive

Se recorrieron las nueve pantallas tanto a 1440 × 1000 como a 390 × 844.
El área útil observada fue de 1425 y 375 px respectivamente, descontando la
barra de desplazamiento. En las 18 comprobaciones el ancho del documento
coincidió con el área útil: sin desbordamiento horizontal.

Se probó el recorrido de formularios en desktop y móvil:
consulta con datos ficticios → verificación → detalle.
El código incorrecto muestra el error; `123456` permite continuar. El reenvío
solo muestra un mensaje. El botón Bold sigue deshabilitado. Se recorrió el
enlace de resultado aprobado y el enlace hacia activación pendiente.
Las nueve pantallas carecen de enlaces fuera de `/portal`.

La revisión visual confirma la tabla como tarjetas en móvil y el resumen
debajo del detalle. El portal no crea una sesión administrativa ni incorpora
sus menús. El OTP sigue siendo ilustrativo: el detalle se puede abrir
directamente, lo cual es admisible únicamente mientras contiene fixtures.

Evidencias locales:
`C:/Users/Sistemas01/.codex/visualizations/2026/10/20/01a1570d-fadd-7432-b51e-6ff60b5e9cf3/`.

- `laravel-validation/routes.json`: colección real y comparación.
- `laravel-validation/http.json`: códigos, tipos y cookies.
- `laravel-validation/visual.json`: resultados de las 18 comprobaciones responsive.
- `laravel-validation/requests.jsonl`: rutas atendidas por el kernel.
- `laravel-desktop.jpg` y `laravel-mobile-portal-proforma.jpg`: detalle.
- `laravel-mobile-portal*.jpg`: capturas de las nueve pantallas en móvil.

El log de Laravel conserva los fallos iniciales de preparación de caché en
Windows, resueltos antes del recorrido. No confundirlos con fallos de las
peticiones verificadas.

## Diagnóstico para sustituir únicamente el detalle

**Viable como una etapa posterior, todavía sin implementar ni consultar BD.**
La evaluación siguiente procede de lectura del código local; no verifica el
esquema, permisos, calidad de datos ni rendimiento de la base real.

### Punto de sustitución

Actualmente `MockPortalController::pantalla()` entrega `PortalFixture::proforma()`
a todas las pantallas. No debe reemplazarse globalmente ese fixture.
La futura dependencia de lectura debe invocarse exclusivamente desde
`proforma()` y entregar un DTO limitado al detalle. Inicio, verificación,
resultado y activación deben seguir aislados y simulados.

Propuesta: un contrato específico de lectura del detalle, una implementación
fixture y una implementación de consultas de solo lectura, activada por
configuración explícita. Las vistas no deben consultar BD.

No basta con cambiar el origen del array: la vista actual tiene el estado
“Pendiente de pago” escrito directamente, fechas ficticias y textos de ejemplo.
La adaptación mínima deberá tomar el estado del DTO, aceptar fechas ausentes
y distinguir claramente los datos reales de las simulaciones. Los enlaces de
resultados ficticios no deben aparentar acreditar pagos de la proforma real.

### Reutilización evaluada

| Código existente | Evaluación |
| --- | --- |
| `ProformasService::findProformaById()` / `findProformasByIds()` | Ruta de lectura de cabecera. Usa `buildProformaLookupQuery()` y joins. Reutilizable tras resolver pertenencia y conexión explícita de solo lectura; no devuelve un DTO público. |
| `ProformasService::monthLabel()` y constantes de meses/estados | Reutilizables sin escrituras. |
| `ProformasService::estadoLabel()` | Lee configuración mediante `EstadoProformaConfigService::getMap()` → `all()`. Es lectura, pero agrega acceso a otra tabla. |
| `EstadoProformaConfigService::syncDefaults()` / `updateColors()` | Escriben con `updateOrInsert`; quedan excluidos. |
| Consulta de `sg_proford` en `ProformaPdfService` | Patrón de lectura reutilizable: `proforma_id`, orden por `orden`. Conviene extraer solo esa lectura a un componente acotado. |
| `ProformaPdfService::generateForProformaId()` | No reutilizar: puede generar archivos y actualizar `rpdf`, `npdf`, `hpdf`. |
| `ProformasService::contextoComprobante()` | Usa `lockForUpdate`; no pertenece al detalle de solo lectura propuesto. |
| Métodos de estados, envío, generación y activación | Excluidos del grafo de llamadas del portal. |

Los servicios actuales usan la conexión predeterminada de `DB`. Una conexión
nombrada READ ONLY no basta si esos métodos siguen usando la conexión global.
Para reutilizarlos con garantías, habría que extraer/injectar el componente de
consulta con conexión explícita, conservando el comportamiento administrativo.
No cambiar globalmente la conexión de la aplicación para resolver este punto.

### Correspondencia de datos

| Detalle público | Fuente propuesta |
| --- | --- |
| Número | `sg_proform.nro_prof` |
| Empresa / documento | `sg_proform.emp`, `sg_proform.nit`, tras comprobar pertenencia |
| Período | `sg_proform.mes`, `sg_proform.anio` |
| Concepto | `sg_proford.descripcion`; código opcional `ref_codigo` |
| Cantidad / unitario / subtotal | `cantidad`, `vr_unidad`, `vr_parcial` |
| Total | `sg_proform.vtotal`, sin recalcular tarifas actuales |
| Estado | `sg_proform.estado` con etiqueta del catálogo existente |
| Emisión / vencimiento | Fuente persistida pendiente de confirmar; omitir si no existe |

No convertir importes decimales a enteros ni recalcular lo ya facturado para
imitar el fixture. Confirmar moneda y precisión; contrastar suma de líneas
con total sin modificar ninguno. La fecha `now()` utilizada al generar el PDF
no demuestra la fecha original de emisión.

El DTO no debe exponer rutas PDF, comprobantes, correo, notas internas,
intentos de envío ni identificadores administrativos innecesarios.

### Condiciones antes de autorizar lecturas reales

1. **Acceso al documento:** NIT y número no constituyen autenticación. El OTP
   fijo y el acceso directo actual no protegen datos reales. La primera prueba
   de lectura debe quedar restringida a un entorno local de revisión autorizado;
   no habilitarla públicamente sin verificación real de pertenencia.
2. **Ambigüedad:** los joins actuales utilizan `MAX(id_cliente)` tanto por
   cobro como por fallback histórico. Pueden elegir un cliente entre varios.
   Para portal se debe exigir una relación única y rechazar ambigüedades,
   documentos ajenos y coincidencias múltiples. El lookup administrativo no
   debe usarse como prueba de autorización.
3. **Permisos:** usar una cuenta/conexión específica con permisos SELECT sobre
   las tablas necesarias, sin escritura ni DDL. El acceso de metadatos que usa
   `Schema::hasColumn` también debe evaluarse. No crear usuarios ni permisos
   automáticamente; entregar cualquier SQL administrativo para revisión manual.
4. **Sin efectos secundarios:** sesiones, caché, auditoría y errores del portal
   no deben escribir en BD; no invocar jobs, listeners de negocio, PDF,
   sincronización de estados, envío ni activación.
5. **Pruebas previas:** contrato/DTO con fixtures; si se necesitan pruebas de
   BD, exclusivamente SQLite `:memory:` y comprobación previa al arranque.
   Las expresiones MySQL del lookup (`BINARY`, `UNSIGNED`) requieren revisión
   específica: una prueba SQLite no certifica su ejecución en MySQL.
6. **Validación posterior autorizada:** revisar esquema, unicidad, permisos
   efectivos y consultas SELECT acotadas. Probar documento ajeno, inexistente,
   ambiguo, sin líneas, estado desconocido e importes decimales. Nunca permitir
   escrituras como parte de esa comprobación.

## Cambios de esta validación

Se agregaron este informe y `tests/Isolated/portal_laravel_validation.php`.
No fue necesario modificar la maqueta aprobada ni las rutas administrativas.
No se implementó todavía la consulta READ ONLY. Sin commit.

# Cadena: generación de resúmenes históricos

## Alcance

Período explícito → originales por categoría → validación → descarga XLSX o ZIP.
El módulo no aplica datos a `valores_externos`, no crea clientes, tablas, proformas ni correos.
La consulta de clientes, cobros y estados de proformas es de lectura. No hay deduplicación histórica persistida.

Las rutas están bajo la autenticación y permisos actuales en `routes/web.php`.
`ImportacionCadenaController::generate` usa la misma preparación que la preview compatible.
Las rutas de preview y descarga por token se conservan, pero no son pasos obligatorios.
El acceso de navegación está en `layouts/admin.blade.php`; no requiere modificar la pantalla de Importaciones.

## Salidas

| Acción | Archivo | Columnas en orden |
|---|---|---|
| Facturas/notas | ResumenCombinado.xlsx | Integracion, Canal, Identificación emisor, Emisor, Facturas de venta, Nota debito, Nota credito |
| Soporte | ResumenDocumentoSoporte.xlsx | Integracion, Canal, Identificación emisor, Emisor, Documento soporte adquisiciones, Nota de ajuste documento soporte adquisiciones |
| Eventos | ResumenEventos.xlsx | Integracion, Canal, Identificación emisor, Emisor, Acuse, Recibo, Aceptación expresa, Aceptación tácita, Reclamo |

Los encabezados y posiciones históricos los define `CadenaExportService`. Las salidas principales no incluyen `cadena_alcance_v1` y pueden leerse con el parser histórico sin incorporar el protocolo de alcance.
Esto implica que la conservación de categorías ausentes no se transmite en estos archivos: Importaciones conserva su tratamiento histórico de vacíos/cero.
`Resumen.xlsx` sigue disponible como alias de compatibilidad en las descargas de preview.

El ZIP requiere las tres categorías e incluye los tres resúmenes comerciales anteriores. Se genera secuencialmente bajo demanda mediante el mismo escritor XLSX, se verifica antes de enviarse y no se entrega incompleto. Las auditorías se descargan por separado; `AuditoriaCadena` se divide en partes de hasta 40.000 filas. La auditoría de soporte usa la procedencia/categoría de las filas, no solo el nombre del archivo.

## Validación y seguridad

- Facturas/notas: 01, 91 y 92; soporte: 05 y 95; eventos: 030–034, con 032 como contador comercial heredado.
- NIT y DV normalizados; no se asignan automáticamente clientes ambiguos. Los errores estructurales y DV inválidos bloquean la descarga. Los NIT ambiguos o sin cliente se exportan sin asignación automática y se advierten al descargar para resolverlos en Importaciones.
- Documentos con identidad: deduplicación por NIT base, tipo, prefijo y número dentro de la carga; hashes de archivo por categoría. Un original puede usarse en soporte y eventos.
- Se mantienen los límites de lector/exportador. El lector XLSX inspecciona contenido efectivo sin expandir columnas vacías declaradas hasta XFD.
- Los tokens de descargas compatibles están cifrados, ligados a sesión/período/archivo y tienen caducidad. Un paquete exige tokens de la misma preparación.
- Los botones y selectores se deshabilitan mientras hay una operación; se restauran en `finally`, incluso ante error. El backend usa `CadenaProcesoUnico` y `CadenaProcesoLock`: bloqueo de archivo no bloqueante por sesión, sin BD. Es una protección local al servidor, no un lock distribuido entre varios servidores.
- Los temporales de generación se limpian al fallar o al enviar la respuesta. Los locks locales, respaldos, evidencias y fixtures reales no se versionan.

## Pruebas incluidas

Las pruebas `tests/Standalone/Cadena{Preview,PositiveCodes,Export,Descargas,Paquete,Generacion,ProcesoLock}Test.php` son aisladas: no arrancan Laravel ni requieren una conexión de BD. Se ejecutan individualmente con PHP y las dependencias existentes en `vendor`.
`node tests/Standalone/CadenaBusyTest.cjs` prueba el JavaScript del formulario con respuestas simuladas, incluyendo doble envío, éxito y errores.
Los cuatro CSV de códigos positivos son sintéticos. Los tests de exportación/descarga invocan solo el parser histórico, sin ejecutar preview comercial ni aplicación.

## Fuera de esta entrega

No incluir `CadenaAlcanceService`, `CadenaAplicacionService`, `CadenaAlcanceTest` ni los cambios pendientes de Importaciones relacionados con alcance o `vlrpaginaweb`. El generador no depende de esos servicios. Tampoco se incluyen propuestas de persistencia Fase 3, cambios de cobros/proformas, evidencias locales ni datos reales de clientes.

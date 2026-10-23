# Aplicación local de pago Bold confirmado

Invocación manual: `php artisan bold:aplicar ID`. No se invoca desde el navegador,
webhook ni conciliador. No hay HTTP, creación de links, activaciones o correos.
El actor lo fija el comando como `cli:bold-aplicar`, nunca el navegador.

Solo APP_ENV local, BOLD_ENV sandbox y BOLD_DB_* dedicados con base exactamente
organizadorprueba. Sin fallback a DB_*. Verifica SELECT DATABASE() y motores
InnoDB de las cuatro tablas involucradas. No modifica estructura; requiere la
ampliación manual aprobada de intentos_pago y sus índices/checks.

Transacción propia con bloqueos FOR UPDATE: intento, proforma, evidencia previa,
cobro por id_cobro exacto y cliente por ID. La PK del cobro garantiza unicidad en
MySQL; el servicio también exige exactamente una fila. No hay fallback por NIT,
período o emisora. NIT se compara solamente para corroborar identidad, no para
buscar otra relación. Se exige id_cliente exacto y sin normalizar IDs ambiguos.

Requiere intento PAGADO/PAID, ambiente verificado, link, referencia, transaction_id,
moneda COP e importe congelado exactamente igual al vtotal, sin floats.
La proforma y el cobro deben tener el mismo estado 2 o 3. Estados distintos,
pagados, facturados, relaciones ausentes/ambiguas o importes distintos provocan
estado_aplicacion REVISION; conservan el estado remoto y no tocan las tablas de
negocio. Un intento no confirmado devuelve NO_CONFIRMADO sin ninguna escritura.

Una aplicación válida modifica solo:

- sg_proform: estado=4, fpago=BOLD, fpag=fecha de aplicación en America/Bogota.
- valores_externos: Proforma=4 en la única fila de id_cobro.
- intentos_pago: evidencia APLICADO, fecha UTC con
  microsegundos, actor, fecha de intento, error NULL y estado anterior.

Comprobante, PDF, importes y retención permanecen intactos. El índice de proforma
aplicada y la unicidad de transacción son defensas adicionales. Toda escritura
condicional debe afectar exactamente una fila. Error/deadlock revierte todo;
devuelve REINTENTAR sin excepciones sensibles. No hay reintento automático.
Si se pierde la respuesta del COMMIT, el siguiente intento encuentra APLICADO
y devuelve YA_APLICADO sin escrituras. APLICADO y REVISION no se reprocesan.
Una proforma posteriormente facturada no se degrada al repetir.

El pago manual actual pasa por contextoComprobante, que bloquea la proforma
dentro de la transacción antes de cambiar su estado. Este servicio vuelve a
leer el estado bajo bloqueo; no depende de una lectura previa del navegador.
No se modifica ese flujo ni se reutiliza su sincronización histórica.

Pruebas aisladas: PHP -n, pdo_sqlite únicamente, SQLite :memory:, sin bootstrap
Laravel/.env. Se prueban aplicación, repetición, facturación posterior, relación
exacta, preservación de campos y rollback después de la escritura de proforma
o cobro. No representan una carrera real de dos procesos MySQL.

La validación funcional en InnoDB debe comprobar dos procesos Artisan simultáneos:
uno aplica y el otro obtiene YA_APLICADO. Para probar rollback, se puede inyectar
una excepción únicamente en el ejecutor de prueba antes de guardar la evidencia,
después de actualizar proforma y cobro; las tres filas deben volver a su estado
inicial y el reintento debe poder completar la aplicación.
Los scripts, registros sintéticos, identificadores y evidencias de esa validación
se mantienen fuera del repositorio. Las pruebas aisladas incluidas solo crean
datos ficticios en SQLite en memoria y no acceden a MySQL.

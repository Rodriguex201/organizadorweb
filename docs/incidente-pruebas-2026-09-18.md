# Incidente durante la implementación de Página web

## Estado documentado al 1 de octubre de 2026

El usuario comunicó posteriormente que ajustó la base manualmente y que ya
existen `vlrpaginaweb` y `0103`. Por su instrucción, la migración
`2026_09_18_000001_add_pagina_web_to_clientes_and_conceptos.php` fue eliminada
del proyecto sin ejecutarla. Esos dos elementos quedaron resueltos por
confirmación del usuario, no por una migración ejecutada por el agente.

Las validaciones posteriores de Cadena pudieron consultar cobros y proformas.
Por tanto, la ausencia de tablas descrita abajo es una observación del incidente,
no una descripción del estado actual. Esas consultas no prueban la recuperación
íntegra de los registros anteriores ni de todas las tablas afectadas.
No consta en este informe una certificación de restauración completa de
`valores_externos`, `config_tarifas` e `importacion_extraccion_logs`.

Esta revisión documental no ejecutó SQL, migraciones ni pruebas. Los resultados
de pruebas y consultas de las secciones siguientes son históricos, no una
validación nueva. Las salvaguardas locales están preparadas para revisión.

## Causa y acciones ejecutadas

El agente ejecutó `php vendor/bin/phpunit` sin comprobar primero que
`bootstrap/cache/config.php` anulaba la configuración SQLite de `phpunit.xml`.
Por ello, pruebas existentes con operaciones `Schema::dropIfExists` y
`Schema::create` alcanzaron la conexión MySQL real. La ejecución se interrumpió
al identificarlo. También se ejecutó una prueba individual de
`CobrosBasePeriodoServiceTest` para diagnosticar el fallo antes de reconocer
que estaba accediendo a la base real.

Las primeras seis pruebas específicas de Página web usaron una conexión
SQLite en memoria explícita, distinta de la conexión real.

## Observaciones históricas del 18 de septiembre de 2026

- `valores_externos` estaba ausente; las pruebas de cobros incluían su eliminación.
- `config_tarifas` fue recreada por `TarifaConfigServiceTest` y contenía datos
  de prueba. La metadata del servidor registraba creación el 18/09/2026 a las
  15:34:13 y actualización a las 15:34:21 (hora del servidor, UTC-05).
- `importacion_extraccion_logs` estaba ausente y las pruebas de importaciones
  incluían su eliminación. No se obtuvo un inventario previo que permita
  confirmar si esa tabla existía antes de esta ejecución.
- `clientes_potenciales`, `sg_proform` y `sg_proford` seguían presentes. La
  eliminación de `clientes_potenciales` fue bloqueada por una clave foránea.
  Esto confirmó su presencia, no constituyó una comparación íntegra de datos
  antes/después.
- El servidor informó `log_bin = 1` y `binlog_format = ROW`. La conexión utilizada
  entonces no tenía permiso para `SHOW BINARY LOGS`.
- Los cinco archivos SQL revisados contenían la base de sistema `mysql`,
  no un respaldo de la base de la aplicación.

Durante esa intervención el agente no ejecutó restauraciones ni intentó
reconstruir registros de negocio con datos inventados. La recomendación de ese
momento fue preservar los registros binarios y recuperar primero en una instancia
separada, sin sobrescribir cambios posteriores. Esta nota conserva esa
recomendación histórica; no afirma que siga pendiente ni que se haya completado.

## Protección incorporada y resultados históricos de validación

- `tests/bootstrap.php` fuerza SQLite en memoria y una ruta de configuración
  cacheada inexistente exclusiva de pruebas.
- `tests/TestCase.php` aborta antes de los preparativos de cada prueba si la
  conexión predeterminada no es SQLite en memoria.
- Se comprobó explícitamente `configuration_cached = false`, `driver = sqlite`
  y `database = :memory:` antes de volver a ejecutar pruebas.
- Pasaron 40 pruebas relacionadas, con 175 aserciones, incluida la generación
  y persistencia independiente de `0103`, valor cero, formulario del cliente,
  revisión, cobro extraordinario, exportación y plantilla PDF.
- Se verificaron los cálculos JavaScript de cliente, revisión y extraordinario
  con importes de Página web de 0 y 250.
- La suite completa aislada ejecutó 166 pruebas: 10 fallos, 1 error y 6 pruebas
  marcadas como riesgosas. No se afirma que la suite completa pase.
- Los archivos PHP modificados pasaron validación de sintaxis y
  `git diff --check` no reportó errores.

## Verificación inicial autorizada (histórica)

Antes de editar archivos se confirmó que `0103` no existía en `conceptos`.
`vlrsoporte`, `vlrfactura` y `vlrextra` eran `INT`, precisión 10, escala 0,
nullable y con valor predeterminado 0. La migración propuesta utilizaba ese mismo
tipo y proponía crear `SERVICIO PAGINA WEB` con `cuenta = NULL`. Fue retirada sin
ejecutarse; prevalece la confirmación posterior del usuario indicada al inicio.

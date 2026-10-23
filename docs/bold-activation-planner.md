# Activación normal: planificación durable y política de un mes

## Alcance

Un pago Bold completamente confirmado y aplicado genera una sola tarea
`ACTIVACION_NORMAL`. Los conceptos se conservan como evidencia informativa;
no determinan servicios, duración ni elegibilidad. No se generan tareas Eventos.

Configuración: `remote_enabled=false`, `rules_version=PAGO_APLICADO_UN_MES_V1`,
`rules=[]`. La fábrica de producción utiliza siempre el adaptador deshabilitado,
que no abre conexiones. Esta fase no incorpora ejecutor remoto, scheduler,
correo ni cambios al pago aplicado o a la presentación/cobro de la proforma.

## Precondición y objetivo

El cliente se obtiene del intento aplicado y se valida con proforma y cobro exacto.
Se comprueban confirmación PAGADO/PAID, ambiente verificado, importe neto persistido,
moneda COP, método BOLD y estado aplicado. `fecha_pago` es la fecha `sg_proform.fpag`
persistida por la aplicación del pago, no la hora de la ejecución del planificador.

La resolución simulable exige inspección exitosa explícita de ambos servidores:
una única ubicación, código exacto del cliente, base derivada estrictamente del
código alfanumérico, un registro individual y uno global. No se corrigen códigos
eliminando caracteres ni se escoge la primera coincidencia. Las cuatro fechas
Y-m-d deben ser válidas, ambas parejas iguales y su separación de dos días.

Política pura (fechas civiles, sin sumar segundos ni 30 días):

```
base = MAX(vencimiento_actual, fecha_pago)
fin_objetivo = base + 1 mes calendario sin overflow
inicio_objetivo = fin_objetivo - 2 días
```

Ejemplos: 31 enero de año común termina 28 febrero; en año bisiesto, 29 febrero.
Los objetivos se corresponden con seg_fecha/emprfpago y seg_maxima/emprfinit.
La planificación conserva las cuatro fechas originales, destino, versión y hash
junto al objetivo. No se recalculan al repetir, cambiar el cliente o cambiar fpag.

## Persistencia, exclusión y fallos

Solo se abre la conexión dedicada BOLD_DB_* a organizadorprueba, local/sandbox,
sin fallback. Se verifica SELECT DATABASE() y motores InnoDB. El código de
persistencia modifica exclusivamente activaciones_pago.

El planificador bloquea el intento y obtiene un bloqueo MySQL por empresa
normalizada ANTES de leer/congelar el objetivo. Si hay otra tarea normal no
finalizada para esa empresa, devuelve REINTENTAR/EMPRESA_OCUPADA sin crear una
segunda tarea. Así otro pago no consume la misma precondición. El bloqueo con
nombre se libera en finally; no se liberan tareas inciertas automáticamente.
Los índices únicos existentes protegen clave estable, intento/servicio y ejecución
por empresa/servicio. El lote procesa un máximo de 50 candidatos por invocación.

Una tarea existente devuelve YA_PLANIFICADO sin mutación ni consulta remota.
Si existen tareas del diseño anterior (PLANIFICACION u otra combinación), devuelve
REVISION_PLAN_LEGADO sin convertirlas, borrarlas o añadir otra tarea. Se requiere
una transición supervisada futura; esta fase no migra datos existentes.

Con remoto deshabilitado, no se fabrican fechas ni destino: se crea una tarea
normal REVISION/LECTURA_REMOTA_NO_DISPONIBLE. El DDL existente exige un objetivo
completo para REINTENTABLE, por lo que ese estado solo se usa DESPUÉS de congelarlo.
No se ejecutó DDL ni se relajaron sus restricciones. Las tareas en revisión no se
reabren automáticamente.

## Comparación posterior (sin transporte real)

reserve() asigna token y lease; la exclusión y el token impiden dos ejecutores.
observe() acepta observaciones internas del adaptador, valida la huella, identidad,
destino y las cuatro fechas. No existe endpoint público que acepte estas observaciones.

- Objetivo completo: COMPLETADA y verificado_at; repetir devuelve YA_COMPLETADA.
- Precondición completa: permite el futuro intento de escritura absoluta; no suma meses.
- Tercer valor, coincidencia parcial, destino ambiguo o distinto: REVISION.
- Consulta fallida antes de posible escritura: REINTENTABLE, espera mínima de cinco minutos.
- Antes de una escritura simulada se persiste INDETERMINADA; pérdida de respuesta
  conserva ese estado y la exclusión. Una nueva lectura debe resolver el resultado.
- Recuperar un lease vencido conserva INDETERMINADA y cambia el token. El ejecutor
  anterior pierde autorización. La recuperación no escribe ni recalcula objetivos.

No se rellena escrito_at por inferencia: ver el objetivo remoto acredita la
verificación, pero no prueba qué ejecutor lo escribió. El futuro transporte deberá
comprobar precondición bajo bloqueo remoto, escribir y releer; todavía no existe.
Ningún fallo de activación revierte pago aplicado, cambia proforma o vuelve a cobrar.

## Uso y pruebas

```
php artisan bold:planificar-activaciones --id=<intento>
php artisan bold:planificar-activaciones --limit=10
```

El comando no permite seleccionar un adaptador real ni un simulador desde argumentos.
Las pruebas inyectan SimulatedActivationAdapter, ubicado exclusivamente en tests;
no tiene PDO, HTTP ni IO y solo modifica su memoria.

Ejecutar aisladas sin Laravel ni drivers MySQL:

```
php -n -d extension_dir=<directorio-ext> -d extension=pdo_sqlite tests/Isolated/bold_activation_planner.php
```

Se cubren año bisiesto, fin de mes/año, pago anticipado/tardío, conceptos repetidos y
desconocidos, falta/duplicidad de registros, fechas divergentes, destino ambiguo,
rollback, idempotencia, token vencido, backoff, pérdida de respuesta, objetivo ya
alcanzado y conservación de COMPLETADA. SQLite se limita a :memory:.

Validación adicional autorizada: fixtures nuevos en MySQL/InnoDB organizadorprueba,
adaptador en memoria, dos procesos/conexiones para planificación y reserva.
Una planificación congela y la otra espera; una reserva gana y la otra se rechaza.
Se limpian únicamente fixtures identificados, en orden de dependencias; las huellas
de las ocho tablas comparadas vuelven al estado inicial. Scripts/evidencias locales
no se incorporan al repositorio y no se reinicia AUTO_INCREMENT.

# Adaptador de escritura normal — preparado, deshabilitado

`config/bold_activations.php` conserva `remote_enabled=false`. No se conecta el
adaptador al comando ni al planificador. No se habilita Eventos. El adaptador no
cambia pagos/proformas y no envía correos. La habilitación requiere autorización
explícita; permanece deshabilitado por defecto.

## Contrato

`NormalActivationWriteAdapter::execute($task)` recibe la tarea persistida:
servicio, código exacto, conexión lógica, base, versión, cuatro fechas anteriores,
dos fechas objetivo y `objetivo_sha256`. Valida la huella del planificador y las
fechas antes de abrir una conexión. Nunca suma meses ni resuelve otro servidor.

La fábrica `PdoNormalActivationWriteConnection::adapter($namedConnections, $policy)`
recibe únicamente configuraciones nombradas explícitas. No lee `DB_*`, una URL,
un DSN ni la conexión por defecto. La bandera deshabilitada bloquea antes de
construir PDO; el constructor PDO también la comprueba. No hay ruta HTTP ni
comando que habilite este transporte. Las pruebas inyectan conexiones en memoria.

## Transacción y recuperación

1. Conectar a la base congelada del único servidor congelado; verificar DATABASE().
2. Transacción SERIALIZABLE; bloquear global e individual en orden constante con
   SELECT FOR UPDATE, sin seleccionar arbitrariamente la primera coincidencia.
3. Exigir una fila de cada tipo, código exacto y ambas tablas InnoDB. Un motor
   heredado no transaccional produce REVISION antes de cualquier UPDATE.
4. Objetivo ya alcanzado: liberar bloqueos, devolver YA_COMPLETADA sin UPDATE.
5. Precondición distinta: REVISION, sin UPDATE.
6. Actualizar solo las cuatro fechas, con predicados de fechas anteriores y código
   global. Exigir exactamente una fila por UPDATE o revertir toda la transacción.
7. Commit; liberar conexión. Abrir otra conexión al mismo destino para verificar
   de nuevo unicidad, código y las cuatro fechas. Solo coincidencia exacta produce
   COMPLETADA. Divergencia observada produce REVISION.

Fallo antes de escribir o fallo con rollback confirmado: REINTENTABLE, salvo una
discrepancia de datos, que produce REVISION. Commit sin confirmación, rollback no
confirmado después de intentar escribir o lectura posterior no disponible:
INDETERMINADA. El siguiente intento siempre relee; nunca vuelve a extender fechas.
Los resultados solo contienen estado y código seguro, sin excepciones originales.

El adaptador devuelve resultados; no persiste estados locales. La futura
coordinación deberá mantener las reservas de `ActivationTaskRepository` y guardar
el resultado con su token, incluyendo YA_COMPLETADA como COMPLETADA. No se debe
liberar una reserva indeterminada para ejecutar otra renovación sin reconciliar.
Los bloqueos InnoDB protegen ejecuciones simultáneas de la misma precondición; las
reservas durables empresa/servicio siguen siendo necesarias entre pagos distintos.

## Validación de esta fase

`php -n tests/Isolated/bold_activation_write.php`: transporte simulado, sin PDO
real ni Laravel. Cubre éxito, segunda lectura, objetivo alcanzado, precondición
alterada, rollback parcial/técnico, commit incierto, recuperación, bloqueo de dos
ejecutores intercalados, huella/destino inválidos y bandera deshabilitada.

La concurrencia se valida mediante simulación del bloqueo. La prueba controlada
de escritura real fue aprobada: precondición exacta, registros únicos, ambas tablas
InnoDB, una fila actualizada por tabla y objetivo verificado desde una conexión
nueva después del commit. No se incluyen identificadores ni evidencias del destino.
La habilitación de esa prueba fue solo en memoria y terminó con el proceso.
Antes de cualquier nueva escritura se requiere autorización específica y comprobar
compatibilidad transaccional y precondición del destino elegido.

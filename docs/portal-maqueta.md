# Portal de clientes — maqueta visual

Esta entrega no integra pagos, correo, base de datos ni activaciones.
No representa autenticación real: todas las pantallas contienen datos ficticios
y son accesibles directamente para revisión.

## Navegación

- `/portal`: inicio; botón para completar los datos de ejemplo.
- `/portal/verificacion`: código ilustrativo `123456`; reenvío simulado.
- `/portal/proforma`: detalle de cuatro conceptos, total de $230.000 COP.
- `/portal/resultado/aprobado`
- `/portal/resultado/pendiente`
- `/portal/resultado/rechazado`
- `/portal/activacion/pendiente`
- `/portal/activacion/completada`
- `/portal/activacion/incidencia`

La sección desplegable «Explorar pantallas de la maqueta» permite visitar
todos los escenarios. El botón Bold está deshabilitado.

## Aislamiento

`bootstrap/app.php` registra `routes/portal.php` mediante el callback `then`.
No se modificó `routes/web.php`. El grupo nuevo no utiliza middleware `web`,
sesiones ni middleware de autorización administrativa.

`App\Portal\Mock\PortalFixture` es la única fuente de datos.
El controlador no utiliza modelos, DB, cache, sesiones, correo ni HTTP externo.
Los campos de formulario no tienen atributo `name`: sus valores no se transmiten.
El JavaScript valida únicamente el código ficticio en el navegador, sin fetch,
almacenamiento local ni cookies.

Las seis plantillas de `resources/views/portal` usan PHP compatible con Blade,
con escape explícito y rutas de inclusión determinadas por el controlador.
Esto permite renderizarlas también de forma aislada, sin iniciar Laravel.

Los recursos CSS/JS viven en `public/portal-mock`. Se eligió CSS independiente
para no recompilar ni modificar el bundle administrativo o depender de una CDN.

## Validación realizada

- Sintaxis PHP mediante `php -n -l`, sin iniciar Laravel.
- Sintaxis JavaScript mediante `node --check`.
- Recorrido visual en servidor PHP aislado, sin autoload, `.env` ni extensiones
  de base de datos; no se ejecutó el front controller de Laravel.
- Revisión de OTP incorrecto/correcto, reenvío ficticio, detalle, pago y activación.
- Revisión responsive de inicio y detalle a 390 px.

La integración de rutas dentro de una instancia Laravel arrancada queda sin
ejecutar en esta revisión para respetar la restricción de acceso a BD.
Antes de reemplazar fixtures por datos reales, esta maqueta necesita un diseño
de autorización efectivo: el código fijo y los enlaces directos son solo demo.

No se realizaron migraciones, cambios de datos ni commit.

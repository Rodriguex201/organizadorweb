<?php
$activaciones = [
    'pendiente' => ['tono' => 'amber', 'icono' => '◷', 'titulo' => 'Estamos preparando tu servicio', 'texto' => 'El pago de ejemplo está aprobado. La activación aparece pendiente; no necesitas realizar otro pago.', 'etiqueta' => 'Activación pendiente'],
    'completada' => ['tono' => 'green', 'icono' => '✓', 'titulo' => 'Todo listo para continuar', 'texto' => 'Así verás la confirmación cuando tus servicios hayan sido activados. En esta vista previa no se realizó ningún cambio.', 'etiqueta' => 'Activación completada'],
    'incidencia' => ['tono' => 'rose', 'icono' => '!', 'titulo' => 'Tu activación necesita revisión', 'texto' => 'El pago de ejemplo está aprobado, pero la activación presenta una incidencia. No vuelvas a pagar: el siguiente paso sería una revisión técnica.', 'etiqueta' => 'Incidencia de activación'],
];
$activacion = $activaciones[$estado];
?>
<section class="narrow result-section">
    <div class="card status-card">
        <span class="status-icon <?= $activacion['tono'] ?>" aria-hidden="true"><?= $activacion['icono'] ?></span><span class="eyebrow">SEGUIMIENTO DEL SERVICIO</span>
        <h1><?= $e($activacion['titulo']) ?></h1><p class="muted"><?= $e($activacion['texto']) ?></p>
        <div class="service-box"><strong><?= $e($proforma['empresa']) ?></strong><span><?= $e($proforma['numero']) ?> · <?= $e($proforma['periodo']) ?></span></div>
        <ol class="timeline">
            <li class="finished"><b>✓</b><div><strong>Pago confirmado</strong><small>Resultado ficticio aprobado</small></div></li>
            <li class="<?= $estado === 'completada' ? 'finished' : 'active' ?>"><b><?= $estado === 'completada' ? '✓' : '2' ?></b><div><strong><?= $e($activacion['etiqueta']) ?></strong><small><?= $estado === 'incidencia' ? 'Requiere revisión técnica · Ejemplo' : 'Procesamiento de los servicios de la proforma' ?></small></div></li>
            <li class="<?= $estado === 'completada' ? 'finished' : '' ?>"><b><?= $estado === 'completada' ? '✓' : '3' ?></b><div><strong>Servicio disponible</strong><small><?= $estado === 'completada' ? 'Confirmación ilustrativa de disponibilidad' : 'Se confirmará al completar la activación' ?></small></div></li>
        </ol>
        <div class="notice info"><strong>Seguimiento de demostración</strong><span>No se ejecutan activaciones ni se envían notificaciones.</span></div>
        <a class="button secondary" href="<?= $e($portalBase) ?>/proforma">Volver a la proforma</a>
    </div>
    <div class="scenario-tabs" aria-label="Escenarios de activación"><?php foreach (['pendiente' => 'Pendiente', 'completada' => 'Completada', 'incidencia' => 'Con incidencia'] as $key => $label): ?><a <?= $estado === $key ? 'aria-current="page"' : '' ?> href="<?= $e($portalBase) ?>/activacion/<?= $key ?>"><?= $label ?></a><?php endforeach ?></div>
</section>

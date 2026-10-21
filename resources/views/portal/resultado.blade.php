<?php
$resultados = [
    'aprobado' => ['tono' => 'green', 'icono' => '✓', 'titulo' => 'Tu pago fue aprobado', 'texto' => 'En el flujo real, este sería el momento de preparar la activación de tus servicios.', 'etiqueta' => 'Pago aprobado', 'accion' => 'Ver estado de activación', 'destino' => '/activacion/pendiente'],
    'pendiente' => ['tono' => 'amber', 'icono' => '◷', 'titulo' => 'Tu pago está pendiente', 'texto' => 'Estamos mostrando un pago aún por confirmar. En el flujo real, espera la confirmación antes de iniciar otro pago.', 'etiqueta' => 'Por confirmar', 'accion' => 'Volver a la proforma', 'destino' => '/proforma'],
    'rechazado' => ['tono' => 'rose', 'icono' => '×', 'titulo' => 'El pago no fue aprobado', 'texto' => 'Este escenario representa un intento rechazado. Puedes volver a revisar la proforma y explorar otro resultado.', 'etiqueta' => 'Pago rechazado', 'accion' => 'Volver a la proforma', 'destino' => '/proforma'],
];
$resultado = $resultados[$estado];
?>
<section class="narrow result-section">
    <div class="card status-card">
        <span class="status-icon <?= $resultado['tono'] ?>" aria-hidden="true"><?= $resultado['icono'] ?></span>
        <span class="eyebrow">RESULTADO SIMULADO</span><h1><?= $e($resultado['titulo']) ?></h1><p class="muted"><?= $e($resultado['texto']) ?></p>
        <div class="receipt"><div><span>Proforma</span><strong><?= $e($proforma['numero']) ?></strong></div><div><span>Valor del intento</span><strong><?= $money($proforma['total']) ?> COP</strong></div><div><span>Estado</span><span class="badge <?= $resultado['tono'] ?>"><?= $e($resultado['etiqueta']) ?></span></div><div><span>Referencia de ejemplo</span><strong>DEMO-001284</strong></div></div>
        <a class="button primary" href="<?= $e($portalBase.$resultado['destino']) ?>"><?= $e($resultado['accion']) ?> →</a>
        <p class="privacy-note">No se contactó a Bold ni se procesó dinero.</p>
    </div>
    <a class="back-link center-link" href="<?= $e($portalBase) ?>/proforma">← Revisar otros resultados</a>
</section>

<a class="back-link" href="<?= $e($portalBase) ?>">← Nueva consulta</a>
<div class="page-heading"><div><div class="eyebrow">TU PROFORMA</div><h1>Todo claro, antes de pagar.</h1><p class="muted">Revisa los servicios incluidos en este período.</p></div><span class="badge amber">● Pendiente de pago</span></div>
<section class="detail-grid">
    <div class="card invoice-card">
        <div class="invoice-top"><div><small class="eyebrow">NÚMERO DE PROFORMA</small><h2><?= $e($proforma['numero']) ?></h2></div><span class="document-symbol" aria-hidden="true">▤</span></div>
        <div class="invoice-meta"><div><small>CLIENTE / EMPRESA</small><strong><?= $e($proforma['empresa']) ?></strong><span>NIT <?= $e($proforma['documento']) ?> · Datos ficticios</span></div><div><small>PERÍODO</small><strong><?= $e($proforma['periodo']) ?></strong><span>Emisión: <?= $e($proforma['emision']) ?></span></div></div>
        <div class="table-wrap"><table><caption class="sr-only">Conceptos y valores de la proforma de ejemplo, en pesos colombianos</caption><thead><tr><th scope="col">Concepto</th><th scope="col">Cant.</th><th scope="col">Valor unitario</th><th scope="col">Subtotal</th></tr></thead><tbody>
        <?php foreach ($proforma['lineas'] as $linea): ?>
            <tr><td><strong><?= $e($linea['concepto']) ?></strong><small><?= $e($linea['descripcion']) ?></small></td><td data-label="Cantidad"><?= $e($linea['cantidad']) ?></td><td data-label="Valor unitario"><?= $money($linea['unitario']) ?></td><td data-label="Subtotal"><?= $money($linea['subtotal']) ?></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <div class="invoice-total"><span>Total de la proforma <small>Valores expresados en COP</small></span><strong><?= $money($proforma['total']) ?></strong></div>
        <p class="invoice-note">Documento de ejemplo para revisión visual. No representa una obligación de pago.</p>
    </div>
    <aside class="card summary-card">
        <div class="eyebrow">RESUMEN</div><h2>Tu próximo paso</h2>
        <div class="amount"><?= $money($proforma['total']) ?><span>COP</span></div>
        <div class="summary-row"><span>Vencimiento de ejemplo</span><strong><?= $e($proforma['vencimiento']) ?></strong></div>
        <button class="button bold-button" type="button" disabled aria-describedby="bold-help">Pagar con <b>Bold</b> <span aria-hidden="true">↗</span></button>
        <p class="field-help" id="bold-help">Disponible en una próxima etapa. En esta maqueta no se realizan cobros.</p>
        <div class="summary-divider"></div>
        <strong class="small-heading">Explora qué sucede después</strong><p class="field-help">Elige un resultado ficticio para revisar el flujo.</p>
        <a class="button secondary" href="<?= $e($portalBase) ?>/resultado/aprobado">Simular pago aprobado →</a>
        <div class="inline-links"><a href="<?= $e($portalBase) ?>/resultado/pendiente">Pendiente</a><a href="<?= $e($portalBase) ?>/resultado/rechazado">Rechazado</a></div>
    </aside>
</section>

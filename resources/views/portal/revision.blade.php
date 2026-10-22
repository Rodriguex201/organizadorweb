<?php
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
// Format decimal strings without float conversion or loss of stored precision.
$money = static function ($value) use ($e): string {
    $parts = explode('.', (string) $value, 2);
    $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[0]);
    $fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';
    return '$ '.$e($integer).($fraction !== '' ? ','.$e(str_pad($fraction, 2, '0')) : '');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Revisión de proforma · RM Soft</title>
    <link rel="stylesheet" href="<?= $e(url('/portal-mock/portal.css')) ?>">
</head>
<body>
<div class="demo-banner">REVISIÓN LOCAL · Consulta de solo lectura</div>
<header class="header container"><a class="brand" href="<?= $e(url('/portal/revision')) ?>">RM Soft <small>PORTAL DE CLIENTES</small></a></header>
<main class="container main">
<?php if ($detail === null): ?>
    <?php if ($otpStage ?? false): ?>
    <section class="card verification-card">
        <div class="eyebrow">VERIFICACIÓN POR CORREO</div>
        <h1><?= !empty($emailChoices) ? 'Selecciona un correo' : 'Ingresa tu código' ?></h1>
        <p><?= $e(\App\Portal\Otp\PortalOtpService::MESSAGE) ?></p>
        <?php if ($otpError ?? false): ?><p role="alert">No fue posible verificar el código. Revisa el código o solicita uno nuevo.</p><?php endif ?>
        <?php if (!empty($emailChoices)): ?>
        <form method="post" action="<?= $e(url('/portal/revision')) ?>">
            <input type="hidden" name="action" value="choose">
            <label for="option">Correo para recibir el código</label>
            <select id="option" name="option" required>
                <?php foreach ($emailChoices as $choice): ?>
                <option value="<?= $e($choice['id']) ?>"><?= $e($choice['label']) ?></option>
                <?php endforeach ?>
            </select>
            <button class="button primary" type="submit">Enviar código al correo elegido</button>
        </form>
        <?php else: ?>
        <form method="post" action="<?= $e(url('/portal/revision')) ?>" autocomplete="off">
            <input type="hidden" name="action" value="verify">
            <label for="code">Código de 8 dígitos</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{8}" maxlength="8" required>
            <button class="button primary" type="submit">Verificar código</button>
        </form>
        <?php endif ?>
        <p>El código vence en 5 minutos. Para reenviar, espera al menos 60 segundos y vuelve a introducir tus datos.</p>
        <a class="back-link" href="<?= $e(url('/portal/revision')) ?>">Solicitar otro código</a>
    </section>
    <?php else: ?>
    <section class="card verification-card">
        <div class="eyebrow">CONSULTA DE PROFORMA</div>
        <h1>Revisa el detalle de tu proforma</h1>
        <p>Ingresa el documento y el número exacto de proforma.</p>
        <?php if ($submitted): ?><p role="alert">No fue posible mostrar la proforma con los datos proporcionados.</p><?php endif ?>
        <form method="post" action="<?= $e(url('/portal/revision')) ?>" autocomplete="off">
            <label for="documento">NIT o cédula</label>
            <input id="documento" name="documento" inputmode="numeric" pattern="[0-9]{5,15}" maxlength="15" required>
            <label for="numero">Número de proforma</label>
            <input id="numero" name="numero" pattern="[A-Za-z0-9-]{1,50}" maxlength="50" required>
            <button class="button primary" type="submit">Solicitar código</button>
        </form>
    </section>
    <?php endif ?>
<?php else: ?>
    <form method="post" action="<?= $e(url('/portal/revision')) ?>">
        <input type="hidden" name="action" value="restart">
        <button class="button" type="submit">← Nueva consulta</button>
    </form>
    <div class="page-heading"><h1>Detalle de proforma</h1><span class="badge"><?= $e($detail['estado']) ?></span></div>
    <section class="card invoice-card">
        <div class="invoice-top"><div><small>NÚMERO DE PROFORMA</small><h2><?= $e($detail['numero']) ?></h2></div></div>
        <div class="invoice-meta"><div><small>EMPRESA / CLIENTE</small><strong><?= $e($detail['empresa']) ?></strong></div><div><small>PERÍODO</small><strong><?= $e($detail['periodo']) ?></strong></div></div>
        <div class="table-wrap"><table>
            <caption class="sr-only">Conceptos de la proforma</caption>
            <thead><tr><th scope="col">Concepto</th><th scope="col">Cantidad</th><th scope="col">Valor unitario</th><th scope="col">Subtotal</th></tr></thead>
            <tbody><?php foreach ($detail['lineas'] as $linea): ?>
                <tr><td><?= $e($linea['concepto']) ?></td><td data-label="Cantidad"><?= $e($linea['cantidad']) ?></td><td data-label="Valor unitario"><?= $money($linea['unitario']) ?></td><td data-label="Subtotal"><?= $money($linea['subtotal']) ?></td></tr>
            <?php endforeach ?></tbody>
        </table></div>
        <?php if (isset($detail['importes']['valor_original'], $detail['importes']['retencion_porcentaje'], $detail['importes']['retencion_valor'])): ?>
        <div class="invoice-total"><span>Valor original</span><strong><?= $money($detail['importes']['valor_original']) ?></strong></div>
        <div class="invoice-total"><span>Retención en la fuente (<?= $e(number_format((float) $detail['importes']['retencion_porcentaje'], 3, ',', '.')) ?> %)</span><strong>− <?= $money($detail['importes']['retencion_valor']) ?></strong></div>
        <?php else: ?><p>Proforma histórica: sin desglose de retención registrado.</p><?php endif ?>
        <div class="invoice-total"><span>Total a pagar</span><strong><?= $money($detail['total']) ?></strong></div>
    </section>
<?php endif ?>
</main>
<footer class="container footer">RM Soft · Revisión local de solo lectura</footer>
</body>
</html>

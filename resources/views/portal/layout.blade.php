<?php
// Plantillas PHP compatibles con Blade; también permiten revisión aislada sin arrancar Laravel.
$e = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => '$ '.number_format($value, 0, ',', '.');
$titulos = ['inicio' => 'Tu servicio, en un solo lugar', 'verificacion' => 'Verifica tu acceso', 'proforma' => 'Detalle de tu proforma', 'resultado' => 'Resultado del pago', 'activacion' => 'Estado de tu servicio'];
$paso = ['inicio' => 1, 'verificacion' => 2, 'proforma' => 3, 'resultado' => 4, 'activacion' => 4][$pantalla];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $e($titulos[$pantalla]) ?> · RM Soft</title>
    <link rel="icon" href="<?= $e($assetBase) ?>/images/logos/LogoRM.png">
    <link rel="stylesheet" href="<?= $e($assetBase) ?>/portal-mock/portal.css">
    <script src="<?= $e($assetBase) ?>/portal-mock/portal.js" defer></script>
</head>
<body>
    <div class="demo-banner"><span class="demo-dot"></span> VISTA PREVIA <span class="banner-divider">/</span> Datos ficticios. Sin pagos ni activaciones reales.</div>
    <header class="header container">
        <a class="brand" href="<?= $e($portalBase) ?>" aria-label="RM Soft — inicio del portal"><span class="brand-mark">rm<span>›</span></span><span>RM Soft<small>PORTAL DE CLIENTES</small></span></a>
        <span class="header-note"><span class="small-lock" aria-hidden="true">◇</span> Tu espacio de servicios</span>
    </header>
    <main class="container main">
        <nav class="steps" aria-label="Etapas de consulta">
            <?php foreach (['Consulta', 'Verificación', 'Proforma', 'Seguimiento'] as $i => $nombre): ?>
                <span class="step <?= $i + 1 === $paso ? 'current' : '' ?> <?= $i + 1 < $paso ? 'done' : '' ?>" <?= $i + 1 === $paso ? 'aria-current="step"' : '' ?>><b><?= $i + 1 < $paso ? '✓' : $i + 1 ?></b><span><?= $e($nombre) ?></span></span>
            <?php endforeach ?>
        </nav>
        <?php require $portalViewRoot.'/'.$pantalla.'.blade.php'; ?>
        <details class="demo-tools">
            <summary>Explorar pantallas de la maqueta <span>Solo demostración</span></summary>
            <div class="demo-links">
                <a href="<?= $e($portalBase) ?>">Inicio</a>
                <a href="<?= $e($portalBase) ?>/verificacion">Verificación</a>
                <a href="<?= $e($portalBase) ?>/proforma">Proforma</a>
                <?php foreach (['aprobado', 'pendiente', 'rechazado'] as $opcion): ?>
                    <a href="<?= $e($portalBase) ?>/resultado/<?= $opcion ?>">Pago <?= $opcion ?></a>
                <?php endforeach ?>
                <?php foreach (['pendiente', 'completada', 'incidencia'] as $opcion): ?>
                    <a href="<?= $e($portalBase) ?>/activacion/<?= $opcion ?>">Activación <?= $opcion ?></a>
                <?php endforeach ?>
            </div>
        </details>
    </main>
    <footer class="container footer"><span>RM Soft <span class="muted">· Tecnología que te acompaña</span></span><span>Maqueta de experiencia · 2026</span></footer>
</body>
</html>

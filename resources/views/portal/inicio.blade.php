<section class="welcome-grid">
    <div class="welcome">
        <div class="eyebrow"><span></span> BIENVENIDO A TU PORTAL</div>
        <h1>Tu servicio,<br>en un solo <em>lugar.</em></h1>
        <p class="lead">Consulta tu proforma y conoce el estado de tus servicios. Claro, sencillo y a tu alcance.</p>
        <div class="benefits">
            <div><span class="feature-icon">01</span><p><strong>Todo el detalle, a la vista</strong><small>Revisa los conceptos y el valor de tu proforma.</small></p></div>
            <div><span class="feature-icon">02</span><p><strong>Un acceso pensado para ti</strong><small>Verifica tu acceso antes de ver la información.</small></p></div>
            <div><span class="feature-icon">03</span><p><strong>Acompaña cada paso</strong><small>Consulta el resultado y la activación del servicio.</small></p></div>
        </div>
        <div class="welcome-foot"><span class="mini-line"></span> Menos trámites. Más tiempo para tu negocio.</div>
    </div>
    <div class="card lookup-card">
        <span class="icon-tile" aria-hidden="true">▤</span>
        <h2>Consulta tu proforma</h2>
        <p class="muted">Ingresa los datos de referencia para comenzar.</p>
        <form data-lookup action="<?= $e($portalBase) ?>/verificacion" method="get">
            <label for="documento">NIT o cédula</label>
            <input id="documento" type="text" inputmode="numeric" pattern="[0-9]{5,15}" maxlength="15" placeholder="Ej. 900123456" required aria-describedby="documento-help">
            <small id="documento-help" class="field-help">Sin puntos, espacios ni dígito de verificación.</small>
            <label for="numero">Número de proforma</label>
            <input id="numero" type="text" maxlength="40" placeholder="Ej. PF-2026-001284" required>
            <button class="button primary" type="submit">Consultar proforma <span aria-hidden="true">→</span></button>
        </form>
        <div class="sample-box"><strong>Prueba el recorrido</strong><p>Usa los datos de ejemplo. No necesitas información real.</p><button class="text-button" type="button" data-fill>Completar datos de ejemplo <span aria-hidden="true">↗</span></button></div>
        <p class="privacy-note">Esta vista previa no consulta ni guarda los datos ingresados.</p>
    </div>
</section>

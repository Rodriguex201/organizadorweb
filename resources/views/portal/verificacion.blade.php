<section class="narrow">
    <a class="back-link" href="<?= $e($portalBase) ?>">← Volver a la consulta</a>
    <div class="card verification-card">
        <span class="icon-tile">✉</span>
        <div class="eyebrow">UN PASO MÁS</div>
        <h1>Verifica tu acceso</h1>
        <p class="muted">Si los datos coinciden con una proforma, recibirás un código en el correo registrado. Por seguridad, no mostramos información del destinatario.</p>
        <div class="notice info"><strong>Envío simulado</strong><span>No se envió ningún correo. Para explorar la maqueta, utiliza el código <b class="demo-code">123456</b>.</span></div>
        <form data-otp action="<?= $e($portalBase) ?>/proforma" method="get">
            <label for="codigo">Código de verificación</label>
            <input class="otp-input" id="codigo" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required aria-describedby="otp-help otp-error">
            <small class="field-help" id="otp-help">Ingresa los 6 dígitos del código de demostración.</small>
            <p id="otp-error" class="error-text" role="alert" hidden></p>
            <button class="button primary" type="submit">Ver detalle de proforma <span aria-hidden="true">→</span></button>
        </form>
        <div class="resend-row"><span>¿Necesitas otro código?</span><button class="text-button" type="button" data-resend>Simular reenvío</button></div>
        <p class="field-help" data-resend-status role="status" aria-live="polite"></p>
    </div>
    <p class="below-card">Verificación ilustrativa. No constituye autenticación real.</p>
</section>

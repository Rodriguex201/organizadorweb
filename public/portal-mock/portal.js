(() => {
    'use strict';
    document.querySelector('[data-fill]')?.addEventListener('click', () => {
        document.getElementById('documento').value = '900123456';
        document.getElementById('numero').value = 'PF-2026-001284';
        document.getElementById('documento').focus();
    });
    document.querySelector('[data-otp]')?.addEventListener('submit', (event) => {
        const input = document.getElementById('codigo');
        const error = document.getElementById('otp-error');
        if (input.value !== '123456') {
            event.preventDefault();
            error.hidden = false;
            error.textContent = 'Para esta demostración utiliza el código 123456.';
            input.setAttribute('aria-invalid', 'true');
            input.focus();
        }
    });
    document.getElementById('codigo')?.addEventListener('input', (event) => {
        event.target.removeAttribute('aria-invalid');
        document.getElementById('otp-error').hidden = true;
    });
    document.querySelector('[data-resend]')?.addEventListener('click', () => {
        document.querySelector('[data-resend-status]').textContent = 'Reenvío simulado. El código de ejemplo sigue siendo 123456. No se envió ningún correo.';
    });
})();

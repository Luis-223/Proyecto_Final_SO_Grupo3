/* SysMonitor Web — funciones comunes para los módulos (fetch con CSRF, formato y escape de HTML). */
(function () {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    /** Escapa texto antes de meterlo en innerHTML (los comandos de procesos pueden traer < > &). */
    function esc(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /** kB => "512 MB" / "1.4 GB" */
    function kb(valor) {
        const n = Number(valor) || 0;
        if (n >= 1024 * 1024) return (n / 1024 / 1024).toFixed(1) + ' GB';
        if (n >= 1024) return Math.round(n / 1024) + ' MB';
        return n + ' kB';
    }

    async function respuesta(r) {
        let datos = null;
        try { datos = await r.json(); } catch (e) { /* respuesta sin JSON */ }
        if (!r.ok) {
            const mensaje = datos?.mensaje ?? datos?.message
                ?? (r.status === 403 ? 'Esta acción requiere rol Administrador.'
                : r.status === 419 ? 'La sesión expiró. Recargue la página.'
                : r.status === 401 ? 'Debe iniciar sesión de nuevo.'
                : `Error ${r.status}`);
            throw new Error(mensaje);
        }
        return datos;
    }

    async function getJson(url) {
        return respuesta(await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }));
    }

    async function postJson(url, cuerpo) {
        return respuesta(await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(cuerpo ?? {}),
        }));
    }

    /** Muestra un aviso temporal arriba del contenido. */
    function aviso(texto, tipo = 'success') {
        const caja = document.createElement('div');
        caja.className = `alert alert-${tipo} sm-aviso`;
        caja.setAttribute('role', 'status');
        caja.textContent = texto;
        document.body.appendChild(caja);
        setTimeout(() => caja.remove(), 4500);
    }

    window.SM = { esc, kb, getJson, postJson, aviso };
})();

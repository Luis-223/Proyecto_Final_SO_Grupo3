/* M3 — Planificación de CPU: captura de procesos, simulación (en el servidor), Gantt y comparativa. */
(function () {
    const raiz = document.getElementById('m3');
    if (!raiz) return;

    const { esc, getJson, postJson, aviso } = window.SM;
    const $ = (id) => document.getElementById(id);
    const urls = { simular: raiz.dataset.urlSimular, guardar: raiz.dataset.urlGuardar, lista: raiz.dataset.urlLista, base: raiz.dataset.urlBase };
    const nombres = JSON.parse(raiz.dataset.algoritmos);
    const colores = ['#1a7a5c', '#2f6fa8', '#c7801a', '#6a4fa3', '#b93a26', '#3d8b8b', '#8a6d3b', '#a64d79', '#4b6b2f', '#5f6965', '#2b4c7e', '#9a5a0a', '#7a3e65', '#356f5a', '#6b5b95'];
    const EJEMPLO = [[0, 5, 3], [1, 3, 1], [2, 8, 4], [3, 6, 2], [4, 2, 5]];
    const DESCRIPCIONES = {
        FCFS: 'Primero en llegar, primero en ser atendido. No expropiativo.',
        SJF: 'Ráfaga más corta primero entre los que ya llegaron. No expropiativo.',
        SRTF: 'Menor tiempo restante primero. Expropiativo: un proceso que llega con menos tiempo restante desaloja al actual.',
        RR: 'Turnos de duración "quantum" en orden de llegada (cola circular). Expropiativo.',
        PRIORIDAD: 'Menor número = mayor prioridad, entre los que ya llegaron. No expropiativo.',
    };
    let ultimaEntrada = null; // lo último simulado con éxito (para guardar)
    let graficaComparativa = null;

    // ---------- Filas de procesos ----------
    function agregarFila(valores = ['', '', '']) {
        const cuerpo = $('filas-procesos');
        if (cuerpo.rows.length >= 15) { aviso('Máximo 15 procesos.', 'warning'); return; }
        const fila = document.createElement('tr');
        fila.innerHTML = `<td class="sm-num fw-semibold"></td>`
            + ['llegada', 'rafaga', 'prioridad'].map((campo, i) =>
                `<td><input type="number" min="${campo === 'rafaga' ? 1 : 0}" max="100" class="form-control sm-mono" data-campo="${campo}" value="${esc(valores[i])}"></td>`).join('')
            + '<td><button type="button" class="btn btn-sm btn-outline-secondary" data-quitar aria-label="Quitar proceso"><i class="bi bi-x-lg"></i></button></td>';
        cuerpo.appendChild(fila);
        renumerar();
    }

    function renumerar() {
        [...$('filas-procesos').rows].forEach((fila, i) => {
            fila.cells[0].innerHTML = `<span class="sm-leyenda" style="background:${colores[i % colores.length]}"></span>P${i + 1}`;
            fila.querySelectorAll('input').forEach((inp) => inp.setAttribute('aria-label', `${inp.dataset.campo} P${i + 1}`));
        });
    }

    function cargarFilas(lista) {
        $('filas-procesos').innerHTML = '';
        lista.forEach((v) => agregarFila(v));
    }

    $('filas-procesos').addEventListener('click', (ev) => {
        const boton = ev.target.closest('[data-quitar]');
        if (!boton) return;
        if ($('filas-procesos').rows.length === 1) { aviso('Debe quedar al menos un proceso.', 'warning'); return; }
        boton.closest('tr').remove();
        renumerar();
    });
    $('agregar').addEventListener('click', () => agregarFila());
    $('ejemplo').addEventListener('click', () => cargarFilas(EJEMPLO));
    $('aleatorio').addEventListener('click', () => {
        const n = 4 + Math.floor(Math.random() * 3);
        const azar = (a, b) => a + Math.floor(Math.random() * (b - a + 1));
        cargarFilas(Array.from({ length: n }, () => [azar(0, 8), azar(1, 9), azar(1, 5)]));
    });

    function leerProcesos() {
        return [...$('filas-procesos').rows].map((fila, i) => {
            const valor = (campo) => fila.querySelector(`[data-campo="${campo}"]`).value.trim();
            const llegada = valor('llegada');
            const rafaga = valor('rafaga');
            if (llegada === '' || rafaga === '') throw new Error(`P${i + 1}: falta llegada o ráfaga.`);
            return { llegada: Number(llegada), rafaga: Number(rafaga), prioridad: Number(valor('prioridad') || 0) };
        });
    }

    const algoritmo = () => document.querySelector('input[name="algoritmo"]:checked').value;
    const mostrarDescripcion = () => { $('descripcion-algoritmo').textContent = DESCRIPCIONES[algoritmo()]; };
    document.querySelectorAll('input[name="algoritmo"]').forEach((r) => r.addEventListener('change', mostrarDescripcion));

    // ---------- Simular ----------
    $('simular').addEventListener('click', async () => {
        let entrada;
        try {
            entrada = { algoritmo: algoritmo(), quantum: Number($('quantum').value) || 2, procesos: leerProcesos() };
        } catch (e) {
            aviso(e.message, 'warning');
            return;
        }
        try {
            const r = await postJson(urls.simular, entrada);
            ultimaEntrada = entrada;
            pintar(r);
            if ($('guardar')) $('guardar').disabled = false;
        } catch (e) {
            aviso(e.message, 'danger');
        }
    });

    function pintar(r) {
        const s = r.simulacion;
        $('titulo-gantt').textContent = nombres[s.algoritmo] + (s.algoritmo === 'RR' ? ` · quantum ${s.quantum}` : '');
        pintarGantt(s.gantt);

        $('resultados').innerHTML = s.resultados.map((f, i) => `<tr>
            <td class="sm-num fw-semibold"><span class="sm-leyenda" style="background:${colores[i % colores.length]}"></span>${esc(f.id)}</td>
            <td class="sm-num">${f.llegada}</td><td class="sm-num">${f.rafaga}</td><td class="sm-num">${f.fin}</td>
            <td class="sm-num">${f.retorno}</td><td class="sm-num">${f.espera}</td></tr>`).join('');
        $('retorno-promedio').textContent = s.retorno_promedio.toFixed(2);
        $('espera-promedio').textContent = s.espera_promedio.toFixed(2);

        const mejor = Math.min(...Object.values(r.comparativa).map((c) => c.espera));
        document.querySelectorAll('#comparativa tr[data-alg]').forEach((fila) => {
            const c = r.comparativa[fila.dataset.alg];
            fila.cells[1].innerHTML = c.espera.toFixed(2) + (c.espera === mejor ? ' <span class="badge text-bg-success">menor</span>' : '');
            fila.cells[2].textContent = c.retorno.toFixed(2);
            fila.classList.toggle('table-active', fila.dataset.alg === s.algoritmo);
        });
        pintarGraficaComparativa(r.comparativa);
    }

    function pintarGantt(gantt) {
        const total = gantt[gantt.length - 1].fin;
        const inicio = gantt[0].inicio; // siempre 0
        const ancho = (t) => (100 * t) / (total - inicio);
        const color = (id) => colores[(Number(id.slice(1)) - 1) % colores.length];

        const tramos = gantt.map((g) => {
            const ocioso = g.proceso === '—';
            return `<div class="sm-gantt-tramo ${ocioso ? 'ocioso' : ''}" style="width:${ancho(g.fin - g.inicio)}%;${ocioso ? '' : `background:${color(g.proceso)}`}" title="${esc(g.proceso === '—' ? 'CPU ociosa' : g.proceso)}: ${g.inicio} → ${g.fin}">${ocioso ? '' : esc(g.proceso)}</div>`;
        }).join('');

        const marcas = [...new Set([inicio, ...gantt.map((g) => g.fin)])]
            .map((t) => `<span style="left:${ancho(t - inicio)}%">${t}</span>`).join('');

        const caja = $('gantt');
        caja.className = 'sm-gantt';
        caja.removeAttribute('style');
        caja.innerHTML = `<div class="sm-gantt-barra">${tramos}</div><div class="sm-gantt-eje">${marcas}</div>`
            + (gantt.some((g) => g.proceso === '—') ? '<div class="sm-fuente mt-1">Zonas rayadas: CPU ociosa (ningún proceso había llegado).</div>' : '');
    }

    function pintarGraficaComparativa(comparativa) {
        const claves = Object.keys(comparativa);
        const datos = {
            labels: claves.map((k) => nombres[k]),
            datasets: [
                { label: 'Espera promedio', data: claves.map((k) => comparativa[k].espera), backgroundColor: '#1a7a5c' },
                { label: 'Retorno promedio', data: claves.map((k) => comparativa[k].retorno), backgroundColor: '#9fcfbd' },
            ],
        };
        if (graficaComparativa) {
            graficaComparativa.data = datos;
            graficaComparativa.update();
            return;
        }
        graficaComparativa = new Chart($('grafica-comparativa'), {
            type: 'bar',
            data: datos,
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12 } } }, scales: { y: { beginAtZero: true } } },
        });
    }

    // ---------- Guardar y consultar ----------
    $('guardar')?.addEventListener('click', async () => {
        if (!ultimaEntrada) return;
        const nombre = prompt('Nombre para la simulación (opcional):', '');
        if (nombre === null) return;
        try {
            const r = await postJson(urls.guardar, { ...ultimaEntrada, nombre: nombre.trim() || null });
            aviso(r.mensaje, 'success');
        } catch (e) {
            aviso(e.message, 'danger');
        }
    });

    const modal = new bootstrap.Modal($('modal-guardadas'));
    $('ver-guardadas').addEventListener('click', async () => {
        $('lista-guardadas').innerHTML = '<tr><td colspan="8" class="sm-vacio">Cargando…</td></tr>';
        modal.show();
        try {
            const lista = await getJson(urls.lista);
            $('lista-guardadas').innerHTML = lista.length
                ? lista.map((s) => `<tr data-id="${s.id}"><td class="sm-num">${s.id}</td><td>${esc(s.nombre ?? '—')}</td>`
                    + `<td>${esc(s.algoritmo)}${s.quantum ? ` q=${s.quantum}` : ''}</td><td class="sm-num">${s.procesos}</td>`
                    + `<td class="text-end sm-num">${s.espera.toFixed(2)}</td><td class="text-end sm-num">${s.retorno.toFixed(2)}</td>`
                    + `<td>${esc(s.usuario)}</td><td class="sm-num">${esc(s.fecha)}</td></tr>`).join('')
                : '<tr><td colspan="8" class="sm-vacio">Todavía no hay simulaciones guardadas.</td></tr>';
        } catch (e) {
            $('lista-guardadas').innerHTML = `<tr><td colspan="8" class="sm-vacio text-danger">${esc(e.message)}</td></tr>`;
        }
    });

    $('lista-guardadas').addEventListener('click', async (ev) => {
        const fila = ev.target.closest('tr[data-id]');
        if (!fila) return;
        try {
            const s = await getJson(`${urls.base}/${fila.dataset.id}`);
            cargarFilas(s.procesos.map((p) => [p.llegada, p.rafaga, p.prioridad]));
            $(`alg-${s.simulacion.algoritmo}`).checked = true;
            $('quantum').value = s.quantum;
            mostrarDescripcion();
            ultimaEntrada = { algoritmo: s.simulacion.algoritmo, quantum: s.quantum, procesos: s.procesos.map(({ llegada, rafaga, prioridad }) => ({ llegada, rafaga, prioridad })) };
            pintar(s);
            modal.hide();
            aviso(`Simulación #${s.id} cargada.`, 'success');
        } catch (e) {
            aviso(e.message, 'danger');
        }
    });

    // Estado inicial: tres filas vacías, como en el mockup
    cargarFilas([['', '', ''], ['', '', ''], ['', '', '']]);
    mostrarDescripcion();
})();

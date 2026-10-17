/* M4 — Interbloqueos: matrices dinámicas, banquero paso a paso (servidor), solicitud y grafo SVG. */
(function () {
    const raiz = document.getElementById('m4');
    if (!raiz) return;

    const { esc, postJson, aviso } = window.SM;
    const $ = (id) => document.getElementById(id);
    const urls = { evaluar: raiz.dataset.urlEvaluar, solicitud: raiz.dataset.urlSolicitud };
    const letra = (j) => String.fromCharCode(65 + j);
    const vec = (v) => `(${v.join(', ')})`;

    const EJEMPLOS = {
        seguro: { // Silberschatz, A=10 B=5 C=7
            asignacion: [[0, 1, 0], [2, 0, 0], [3, 0, 2], [2, 1, 1], [0, 0, 2]],
            maximo: [[7, 5, 3], [3, 2, 2], [9, 0, 2], [2, 2, 2], [4, 3, 3]],
            disponible: [3, 3, 2],
        },
        bloqueo: { // espera circular: cada proceso tiene un recurso y pide el del siguiente
            asignacion: [[1, 0, 0], [0, 1, 0], [0, 0, 1]],
            maximo: [[1, 1, 0], [0, 1, 1], [1, 0, 1]],
            disponible: [0, 0, 0],
        },
    };

    // ---------- Construcción de las matrices ----------
    function dims() {
        const n = Math.min(10, Math.max(1, Number($('n').value) || 1));
        const m = Math.min(6, Math.max(1, Number($('m').value) || 1));
        return { n, m };
    }

    function construir(valores = null) {
        const { n, m } = dims();
        const previo = valores ?? leer(true);
        const cols = `grid-template-columns: 40px repeat(${m}, minmax(0, 1fr));`;
        const cabecera = '<span></span>' + Array.from({ length: m }, (_, j) => `<span class="text-center sm-fuente">${letra(j)}</span>`).join('');

        ['asignacion', 'maximo', 'necesidad'].forEach((clave) => {
            let html = cabecera;
            for (let i = 0; i < n; i++) {
                html += `<span class="sm-num fw-semibold">P${i}</span>`;
                for (let j = 0; j < m; j++) {
                    if (clave === 'necesidad') {
                        html += `<span class="sm-celda" data-nec="${i}-${j}">—</span>`;
                    } else {
                        const v = previo?.[clave]?.[i]?.[j] ?? '';
                        html += `<input type="number" min="0" max="99" class="form-control" data-matriz="${clave}" data-i="${i}" data-j="${j}" value="${esc(v)}" aria-label="${clave} P${i} ${letra(j)}">`;
                    }
                }
            }
            const caja = $('matriz-' + clave);
            caja.setAttribute('style', cols);
            caja.innerHTML = html;
        });

        const disp = $('vector-disponible');
        disp.setAttribute('style', `grid-template-columns: repeat(${m}, minmax(0, 1fr));`);
        disp.innerHTML = Array.from({ length: m }, (_, j) => `<span class="text-center sm-fuente">${letra(j)}</span>`).join('')
            + Array.from({ length: m }, (_, j) => `<input type="number" min="0" max="99" class="form-control" data-disp="${j}" value="${esc(previo?.disponible?.[j] ?? '')}" aria-label="Disponible ${letra(j)}">`).join('');

        $('proceso-solicitud').innerHTML = Array.from({ length: n }, (_, i) => `<option value="${i}">P${i}</option>`).join('');
        $('pedido').innerHTML = Array.from({ length: m }, (_, j) =>
            `<input type="number" min="0" max="99" class="form-control sm-mono" style="width: 58px" placeholder="${letra(j)}" data-pedido="${j}" aria-label="Solicitud ${letra(j)}">`).join('');

        actualizarNecesidadLocal();
    }

    /** Lee las matrices. Con tolerante=true no exige celdas llenas (para reconstruir al cambiar n o m). */
    function leer(tolerante = false) {
        const { n, m } = dims();
        const celda = (sel) => {
            const el = document.querySelector(sel);
            if (!el) return '';
            if (el.value.trim() === '') {
                if (tolerante) return '';
                el.focus();
                throw new Error('Complete todas las celdas de Asignación, Máximo y Disponible.');
            }
            return Number(el.value);
        };
        const matriz = (clave) => Array.from({ length: n }, (_, i) => Array.from({ length: m }, (_, j) => celda(`[data-matriz="${clave}"][data-i="${i}"][data-j="${j}"]`)));
        return { asignacion: matriz('asignacion'), maximo: matriz('maximo'), disponible: Array.from({ length: m }, (_, j) => celda(`[data-disp="${j}"]`)) };
    }

    /** Necesidad y totales calculados al escribir (el servidor los vuelve a calcular al evaluar). */
    function actualizarNecesidadLocal() {
        const d = leer(true);
        d.asignacion.forEach((fila, i) => fila.forEach((a, j) => {
            const x = d.maximo[i][j];
            const celda = document.querySelector(`[data-nec="${i}-${j}"]`);
            const valido = a !== '' && x !== '';
            celda.textContent = valido ? x - a : '—';
            celda.style.borderColor = valido && x - a < 0 ? '#b93a26' : '';
        }));
        const totales = d.disponible.map((v, j) => (v === '' ? '?' : d.asignacion.reduce((s, f) => s + (Number(f[j]) || 0), Number(v))));
        $('totales').textContent = 'Total por recurso: ' + totales.map((t, j) => `${letra(j)}=${t}`).join(' · ');
    }

    raiz.addEventListener('input', (ev) => {
        if (ev.target.matches('[data-matriz], [data-disp]')) actualizarNecesidadLocal();
    });
    ['n', 'm'].forEach((id) => $(id).addEventListener('change', () => { construir(); limpiar(); }));

    function cargarEjemplo(e) {
        $('n').value = e.asignacion.length;
        $('m').value = e.disponible.length;
        construir(e);
        limpiar();
        evaluar();
    }
    $('ejemplo-seguro').addEventListener('click', () => cargarEjemplo(EJEMPLOS.seguro));
    $('ejemplo-bloqueo').addEventListener('click', () => cargarEjemplo(EJEMPLOS.bloqueo));

    function limpiar() {
        $('estado-texto').textContent = '—';
        $('estado-sistema').style.background = '#f7f8f6';
        $('pasos').innerHTML = '<tr><td colspan="5" class="sm-vacio">Ingrese las matrices y presione “Evaluar estado”.</td></tr>';
        $('grafo').className = 'sm-area-vacia';
        $('grafo').innerHTML = 'El grafo se dibuja al evaluar el estado.';
        ['ciclos', 'sugerencia-terminacion', 'sugerencia-apropiacion'].forEach((id) => { $(id).textContent = '—'; });
        $('nota-ciclos').textContent = '';
        $('resultado-solicitud').className = 'sm-area-vacia mt-auto';
        $('resultado-solicitud').textContent = 'El resultado de la solicitud aparecerá aquí.';
    }

    // ---------- Evaluar estado ----------
    async function evaluar() {
        let datos;
        try { datos = leer(); } catch (e) { aviso(e.message, 'warning'); return; }
        try {
            pintar(await postJson(urls.evaluar, datos));
        } catch (e) {
            aviso(e.message, 'danger');
        }
    }
    $('evaluar').addEventListener('click', evaluar);

    function pintar(r) {
        const s = r.seguridad;
        r.estado.necesidad.forEach((fila, i) => fila.forEach((v, j) => {
            document.querySelector(`[data-nec="${i}-${j}"]`).textContent = v;
        }));

        $('estado-texto').innerHTML = s.seguro
            ? `<span class="text-success fw-semibold">SEGURO</span><br>Secuencia segura: ⟨${s.secuencia.join(', ')}⟩`
            : `<span class="text-danger fw-semibold">INSEGURO</span><br>No pueden terminar: ${s.bloqueados.map((i) => 'P' + i).join(', ')}`
              + (s.secuencia.length ? `<br>Alcanzan a terminar: ⟨${s.secuencia.join(', ')}⟩` : '');
        $('estado-sistema').style.background = s.seguro ? '#eef7f2' : '#fbeeeb';

        $('pasos').innerHTML = s.pasos.map((p) => `<tr class="${p.cabe ? 'paso-si' : ''}">
            <td class="sm-num">${p.paso}</td>
            <td class="sm-num fw-semibold">${esc(p.proceso)}</td>
            <td class="sm-mono">${vec(p.necesidad)} ${p.cabe ? '≤' : '≰'} ${vec(p.trabajo)}</td>
            <td class="sm-mono">${p.trabajo_nuevo ? vec(p.trabajo_nuevo) : '—'}</td>
            <td>${p.cabe ? '<span class="badge text-bg-success">termina</span>' : '<span class="badge text-bg-secondary">espera</span>'}</td>
        </tr>`).join('');

        const ciclos = r.grafo.ciclos;
        $('ciclos').innerHTML = ciclos.length
            ? ciclos.map((c) => esc([...c, c[0]].join(' → '))).join('<br>')
            : 'Ninguno';
        $('nota-ciclos').textContent = ciclos.length && s.seguro
            ? 'Hay ciclo pero NO hay interbloqueo: con varias instancias por recurso, un ciclo es condición necesaria pero no suficiente.'
            : (!s.seguro && !ciclos.length ? 'Estado inseguro sin ciclo en el grafo: todavía no hay interbloqueo, pero podría ocurrir si los procesos piden su máximo.' : '');
        $('sugerencia-terminacion').textContent = r.recuperacion.terminacion;
        $('sugerencia-apropiacion').textContent = r.recuperacion.apropiacion;

        dibujarGrafo(r.grafo, s.bloqueados);
    }

    // ---------- Solicitud ----------
    $('evaluar-solicitud').addEventListener('click', async () => {
        let datos;
        try { datos = leer(); } catch (e) { aviso(e.message, 'warning'); return; }
        const { m } = dims();
        datos.proceso = Number($('proceso-solicitud').value);
        datos.pedido = Array.from({ length: m }, (_, j) => Number(document.querySelector(`[data-pedido="${j}"]`).value) || 0);

        const caja = $('resultado-solicitud');
        try {
            const r = await postJson(urls.solicitud, datos);
            caja.className = 'p-3 rounded mt-auto';
            caja.style.background = r.concedida ? '#eef7f2' : '#fbeeeb';
            caja.innerHTML = `<div class="fw-semibold ${r.concedida ? 'text-success' : 'text-danger'}">P${datos.proceso} solicita ${vec(datos.pedido)}: ${r.concedida ? 'SE CONCEDE' : 'NO SE CONCEDE'}</div>`
                + `<div style="font-size: 14px">${esc(r.motivo)}</div>`
                + (r.estado ? `<div class="sm-fuente mt-1">Disponible quedaría en ${vec(r.estado.disponible)}</div>` : '');
        } catch (e) {
            aviso(e.message, 'danger');
        }
    });

    // ---------- Grafo SVG ----------
    function dibujarGrafo(grafo, bloqueados) {
        const procesos = grafo.nodos.filter((n) => n.tipo === 'proceso');
        const recursos = grafo.nodos.filter((n) => n.tipo === 'recurso');
        const W = 680;
        const H = 300;
        const pos = {};
        const repartir = (lista, y) => lista.forEach((n, k) => { pos[n.id] = { x: ((k + 1) * W) / (lista.length + 1), y }; });
        repartir(procesos, 60);
        repartir(recursos, 235);

        const enCiclo = new Set();
        grafo.ciclos.forEach((c) => c.forEach((nodo, k) => enCiclo.add(`${nodo}>${c[(k + 1) % c.length]}`)));
        const bloq = new Set(bloqueados.map((i) => 'P' + i));

        const flechas = grafo.aristas.map((a) => {
            const p1 = pos[a.de];
            const p2 = pos[a.a];
            const dx = p2.x - p1.x;
            const dy = p2.y - p1.y;
            const largo = Math.hypot(dx, dy) || 1;
            const ux = dx / largo;
            const uy = dy / largo;
            // Las aristas de ida y vuelta entre el mismo par se curvan hacia lados opuestos
            const curva = a.tipo === 'asignacion' ? 22 : -22;
            const x1 = p1.x + ux * 24;
            const y1 = p1.y + uy * 24;
            const x2 = p2.x - ux * 26;
            const y2 = p2.y - uy * 26;
            const cx = (x1 + x2) / 2 - uy * curva;
            const cy = (y1 + y2) / 2 + ux * curva;
            const rojo = enCiclo.has(`${a.de}>${a.a}`);
            const color = rojo ? '#b93a26' : (a.tipo === 'asignacion' ? '#1a7a5c' : '#8a9893');
            return `<path d="M${x1},${y1} Q${cx},${cy} ${x2},${y2}" fill="none" stroke="${color}" stroke-width="${rojo ? 2.4 : 1.6}"`
                + `${a.tipo === 'solicitud' ? ' stroke-dasharray="6 4"' : ''} marker-end="url(#punta-${rojo ? 'rojo' : a.tipo})"/>`
                + `<text x="${(x1 + x2) / 2 - uy * curva * 0.6}" y="${(y1 + y2) / 2 + ux * curva * 0.6}" font-size="11" font-family="IBM Plex Mono, monospace" fill="${color}" text-anchor="middle">${a.cantidad}</text>`;
        }).join('');

        const nodosP = procesos.map((p) => `<g><circle cx="${pos[p.id].x}" cy="${pos[p.id].y}" r="22" fill="${bloq.has(p.id) ? '#fbeeeb' : '#fff'}" stroke="${bloq.has(p.id) ? '#b93a26' : '#1c2321'}" stroke-width="1.6"/>`
            + `<text x="${pos[p.id].x}" y="${pos[p.id].y + 4}" text-anchor="middle" font-size="13" font-weight="600" font-family="IBM Plex Mono, monospace">${esc(p.id)}</text></g>`).join('');
        const nodosR = recursos.map((r) => `<g><rect x="${pos[r.id].x - 22}" y="${pos[r.id].y - 22}" width="44" height="44" rx="4" fill="#eef7f2" stroke="#1a7a5c" stroke-width="1.6"/>`
            + `<text x="${pos[r.id].x}" y="${pos[r.id].y + 5}" text-anchor="middle" font-size="14" font-weight="600" font-family="IBM Plex Mono, monospace">${esc(r.id)}</text>`
            + `<text x="${pos[r.id].x}" y="${pos[r.id].y + 40}" text-anchor="middle" font-size="11" fill="#5f6965" font-family="IBM Plex Mono, monospace">${r.instancias} inst.</text></g>`).join('');

        const marcador = (id, color) => `<marker id="punta-${id}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="${color}"/></marker>`;

        const caja = $('grafo');
        caja.className = '';
        caja.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Grafo de asignación de recursos">`
            + `<defs>${marcador('asignacion', '#1a7a5c')}${marcador('solicitud', '#8a9893')}${marcador('rojo', '#b93a26')}</defs>`
            + flechas + nodosP + nodosR + '</svg>'
            + '<div class="sm-fuente mt-1">Verde: asignación (R → P) · gris punteado: P espera R porque su Necesidad supera lo Disponible · rojo: arista en un ciclo · procesos en rojo: no pueden terminar.</div>';
    }

    construir();
})();

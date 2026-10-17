/* M1 — Procesos: tabla con búsqueda y orden, árbol padre-hijo y acciones sobre procesos de prueba. */
(function () {
    const raiz = document.getElementById('m1');
    if (!raiz) return;

    const { esc, kb, getJson, postJson, aviso } = window.SM;
    const $ = (id) => document.getElementById(id);
    const urls = { datos: raiz.dataset.urlDatos, lanzar: raiz.dataset.urlLanzar, base: raiz.dataset.urlBase };
    const esAdmin = raiz.dataset.admin === '1';

    const estado = {
        procesos: [],
        porPid: new Map(),
        orden: { campo: 'cpu', dir: 'desc' },
        seleccionado: null,
        cerrados: new Set(), // nodos del árbol contraídos
        cargando: false,
    };

    // ---------- Lectura ----------
    async function cargar() {
        if (estado.cargando) return;
        estado.cargando = true;
        try {
            const d = await getJson(urls.datos);
            estado.procesos = d.procesos;
            estado.porPid = new Map(d.procesos.map((p) => [p.pid, p]));
            pintarResumen(d);
            pintarTabla();
            pintarArbol();
            pintarPruebas(d.pruebas);
            pintarSeleccion();
            $('ultima-lectura').textContent = d.hora;
        } catch (e) {
            $('ultima-lectura').textContent = 'Error: ' + e.message;
        } finally {
            estado.cargando = false;
        }
    }

    function pintarResumen(d) {
        ['R', 'S', 'D', 'Z', 'T', 'I'].forEach((e) => { $('total-' + e).textContent = d.resumen[e] ?? 0; });
        $('total-procesos').textContent = `${d.procesos.length} procesos`;
    }

    // ---------- Tabla ----------
    function visibles() {
        const texto = $('buscar').value.trim().toLowerCase();
        const filtro = $('filtro-estado').value;
        const { campo, dir } = estado.orden;
        const signo = dir === 'asc' ? 1 : -1;

        return estado.procesos
            .filter((p) => !filtro || (filtro === 'prueba' ? p.prueba : p.estado === filtro))
            .filter((p) => !texto || String(p.pid).includes(texto) || p.usuario.toLowerCase().includes(texto) || p.comando.toLowerCase().includes(texto))
            .sort((a, b) => {
                const x = a[campo];
                const y = b[campo];
                const r = typeof x === 'number' ? x - y : String(x).localeCompare(String(y));
                return r * signo || a.pid - b.pid;
            });
    }

    function pintarTabla() {
        const filas = visibles();
        const cuerpo = $('tabla-procesos').querySelector('tbody');
        if (!filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="8" class="sm-vacio">No hay procesos que coincidan.</td></tr>';
            return;
        }
        cuerpo.innerHTML = filas.map((p) => `
            <tr data-pid="${p.pid}" class="${p.pid === estado.seleccionado ? 'seleccionado' : ''}">
                <td class="sm-num">${p.pid}${p.prueba ? '<span class="sm-badge-prueba">prueba</span>' : ''}</td>
                <td class="sm-num">${p.ppid}</td>
                <td>${esc(p.usuario)}</td>
                <td><span class="sm-estado sm-estado-${esc(p.estado)}" style="${'RSDZT'.includes(p.estado) ? '' : 'background:#a3aca8'}">${esc(p.estado)}</span></td>
                <td class="sm-num">${p.nice}</td>
                <td class="sm-num">${p.cpu.toFixed(1)}</td>
                <td class="sm-num">${kb(p.mem_kb)}</td>
                <td class="sm-mono sm-comando" title="${esc(p.comando)}">${esc(p.comando)}</td>
            </tr>`).join('');
    }

    $('tabla-procesos').querySelector('thead').addEventListener('click', (ev) => {
        const th = ev.target.closest('th[data-orden]');
        if (!th) return;
        const campo = th.dataset.orden;
        estado.orden = {
            campo,
            dir: estado.orden.campo === campo && estado.orden.dir === 'desc' ? 'asc' : (estado.orden.campo === campo ? 'desc' : (['cpu', 'mem_kb', 'nice'].includes(campo) ? 'desc' : 'asc')),
        };
        marcarOrden();
        pintarTabla();
    });

    function marcarOrden() {
        document.querySelectorAll('#tabla-procesos th[data-orden]').forEach((th) => {
            th.classList.remove('orden-asc', 'orden-desc');
            if (th.dataset.orden === estado.orden.campo) th.classList.add('orden-' + estado.orden.dir);
        });
    }

    $('tabla-procesos').querySelector('tbody').addEventListener('click', (ev) => {
        const fila = ev.target.closest('tr[data-pid]');
        if (fila) seleccionar(Number(fila.dataset.pid));
    });
    $('buscar').addEventListener('input', pintarTabla);
    $('filtro-estado').addEventListener('change', pintarTabla);

    document.querySelectorAll('[data-filtro-estado]').forEach((chip) => chip.addEventListener('click', () => {
        const filtro = $('filtro-estado');
        filtro.value = filtro.value === chip.dataset.filtroEstado ? '' : chip.dataset.filtroEstado;
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#vista-tabla"]')).show();
        pintarTabla();
    }));

    // ---------- Árbol ----------
    function pintarArbol() {
        const ocultarKernel = $('ocultar-kernel').checked;
        const lista = estado.procesos.filter((p) => !(ocultarKernel && p.kernel));
        const pids = new Set(lista.map((p) => p.pid));
        const hijos = new Map();
        lista.forEach((p) => {
            const padre = pids.has(p.ppid) ? p.ppid : 0; // sin padre visible => raíz
            if (!hijos.has(padre)) hijos.set(padre, []);
            hijos.get(padre).push(p);
        });
        hijos.forEach((h) => h.sort((a, b) => a.pid - b.pid));

        const nodo = (p) => {
            const suyos = hijos.get(p.pid) ?? [];
            const plegar = suyos.length
                ? `<span class="plegar" data-plegar="${p.pid}">${estado.cerrados.has(p.pid) ? '▸' : '▾'}</span>`
                : '<span class="plegar"></span>';
            return `<li class="${estado.cerrados.has(p.pid) ? 'cerrado' : ''}">${plegar}`
                + `<span class="nodo ${p.pid === estado.seleccionado ? 'bg-success-subtle' : ''}" data-pid="${p.pid}">`
                + `<span class="sm-estado sm-estado-${esc(p.estado)}" style="width:18px;height:18px;line-height:18px;font-size:10px;${'RSDZT'.includes(p.estado) ? '' : 'background:#a3aca8'}">${esc(p.estado)}</span> `
                + `<strong>${p.pid}</strong> ${esc(p.nombre)} <span class="text-secondary">· ${esc(p.usuario)}</span>`
                + (p.prueba ? '<span class="sm-badge-prueba">prueba</span>' : '')
                + (suyos.length ? ` <span class="text-secondary">(${suyos.length})</span>` : '')
                + `</span>${suyos.length ? `<ul>${suyos.map(nodo).join('')}</ul>` : ''}</li>`;
        };

        const raices = hijos.get(0) ?? [];
        $('arbol-procesos').innerHTML = raices.length ? `<ul>${raices.map(nodo).join('')}</ul>` : '<div class="sm-area-vacia">No hay procesos.</div>';
    }

    $('arbol-procesos').addEventListener('click', (ev) => {
        const plegar = ev.target.closest('[data-plegar]');
        if (plegar) {
            const pid = Number(plegar.dataset.plegar);
            estado.cerrados.has(pid) ? estado.cerrados.delete(pid) : estado.cerrados.add(pid);
            pintarArbol();
            return;
        }
        const n = ev.target.closest('.nodo[data-pid]');
        if (n) seleccionar(Number(n.dataset.pid));
    });
    $('expandir').addEventListener('click', () => { estado.cerrados.clear(); pintarArbol(); });
    $('contraer').addEventListener('click', () => {
        estado.procesos.forEach((p) => { if (p.pid !== 1) estado.cerrados.add(p.pid); });
        pintarArbol();
    });
    $('ocultar-kernel').addEventListener('change', pintarArbol);

    // ---------- Selección y acciones ----------
    function seleccionar(pid) {
        estado.seleccionado = pid;
        pintarSeleccion();
        pintarTabla();
        pintarArbol();
    }

    function pintarSeleccion() {
        const p = estado.porPid.get(estado.seleccionado);
        const habilitar = esAdmin && p && p.prueba;
        $('proceso-seleccionado').textContent = p ? `PID ${p.pid}` : (estado.seleccionado ? `PID ${estado.seleccionado} (terminó)` : '—');
        $('detalle-seleccionado').innerHTML = p
            ? `${esc(p.comando)}<br>Estado <strong>${esc(p.estado)}</strong> · nice <strong>${p.nice}</strong> · ${esc(p.usuario)}`
              + (p.prueba ? '<br><span class="text-success">Proceso de prueba: acepta señales y renice.</span>'
                : '<br><span class="text-secondary">No es un proceso de prueba: solo lectura.</span>')
            : 'Haga clic en una fila. Solo los procesos de prueba lanzados por la aplicación aceptan acciones.';

        if (!esAdmin) return;
        document.querySelectorAll('[data-senal]').forEach((b) => { b.disabled = !habilitar; });
        $('nice').disabled = !habilitar;
        $('aplicar-renice').disabled = !habilitar;
        if (habilitar && document.activeElement !== $('nice')) {
            $('nice').value = Math.max(0, p.nice);
            $('nice-valor').textContent = $('nice').value;
        }
    }

    async function accion(promesa) {
        try {
            const r = await promesa;
            aviso(r.mensaje, 'success');
        } catch (e) {
            aviso(e.message, 'danger');
        }
        await cargar();
    }

    if (esAdmin) {
        document.querySelectorAll('[data-senal]').forEach((b) => b.addEventListener('click', () => {
            const pid = estado.seleccionado;
            if (b.dataset.senal === 'KILL' && !confirm(`¿Enviar SIGKILL al PID ${pid}? El proceso termina sin poder limpiar.`)) return;
            accion(postJson(`${urls.base}/${pid}/senal`, { senal: b.dataset.senal }));
        }));
        $('nice').addEventListener('input', () => { $('nice-valor').textContent = $('nice').value; });
        $('aplicar-renice').addEventListener('click', () => {
            accion(postJson(`${urls.base}/${estado.seleccionado}/renice`, { nice: Number($('nice').value) }));
        });
        document.querySelectorAll('[data-lanzar]').forEach((b) => b.addEventListener('click', async () => {
            try {
                const r = await postJson(urls.lanzar, { tipo: b.dataset.lanzar });
                aviso(r.mensaje, 'success');
                estado.seleccionado = r.pid;
            } catch (e) {
                aviso(e.message, 'danger');
            }
            await cargar();
        }));
    }

    function pintarPruebas(pruebas) {
        $('lista-pruebas').innerHTML = pruebas.length
            ? pruebas.map((p) => `<button type="button" class="btn btn-sm btn-light text-start sm-mono" data-pid="${p.pid}">`
                + `PID ${p.pid} · ${p.tipo === 'sleep' ? 'sleep' : 'carga CPU'} · ${esc(estado.porPid.get(p.pid)?.estado ?? '?')} · desde ${esc(p.desde)}</button>`).join('')
            : '<span class="text-secondary">Ninguno.</span>';
    }
    $('lista-pruebas').addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-pid]');
        if (b) seleccionar(Number(b.dataset.pid));
    });

    // ---------- Actualización automática ----------
    $('refrescar').addEventListener('click', cargar);
    setInterval(() => { if ($('auto').checked && !document.hidden) cargar(); }, 5000);

    marcarOrden();
    cargar();
})();

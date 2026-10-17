/* M2 — CPU y Memoria: pide /api/cpu-memoria cada 2–5 s y actualiza indicadores, gráficas y top 5. */
(function () {
    const raiz = document.getElementById('m2');
    if (!raiz) return;

    const { esc, kb, getJson } = window.SM;
    const url = raiz.dataset.url;
    const PUNTOS = 40; // lecturas visibles en la gráfica
    const colores = ['#2f6fa8', '#c7801a', '#6a4fa3', '#b93a26', '#3d8b8b', '#8a6d3b', '#5f6965', '#a64d79'];
    const $ = (id) => document.getElementById(id);

    // Gráfica de CPU: una línea total + una por núcleo
    const nucleos = Number(raiz.dataset.nucleos) || 1;
    const lineas = [{ label: 'Total', data: [], borderColor: '#1a7a5c', borderWidth: 2.5, pointRadius: 0, tension: 0.3 }];
    for (let i = 0; i < nucleos; i++) {
        lineas.push({ label: `cpu${i}`, data: [], borderColor: colores[i % colores.length], borderWidth: 1.2, pointRadius: 0, tension: 0.3 });
    }
    const graficaCpu = new Chart($('grafica-cpu'), {
        type: 'line',
        data: { labels: [], datasets: lineas },
        options: {
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            scales: { y: { min: 0, max: 100, ticks: { callback: (v) => v + ' %' } }, x: { ticks: { maxTicksLimit: 8 } } },
            plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 14, boxHeight: 2 } } },
        },
    });

    // Historial pequeño de memoria usada
    const graficaMem = new Chart($('grafica-mem'), {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'RAM usada %', data: [], borderColor: '#1a7a5c', backgroundColor: 'rgba(26,122,92,.12)', fill: true, borderWidth: 1.5, pointRadius: 0, tension: 0.3 }] },
        options: {
            maintainAspectRatio: false,
            animation: false,
            scales: { y: { min: 0, max: 100, ticks: { callback: (v) => v + ' %', maxTicksLimit: 3 } }, x: { display: false } },
            plugins: { legend: { display: false } },
        },
    });

    function agregar(grafica, etiqueta, valores) {
        grafica.data.labels.push(etiqueta);
        valores.forEach((v, i) => grafica.data.datasets[i]?.data.push(v));
        if (grafica.data.labels.length > PUNTOS) {
            grafica.data.labels.shift();
            grafica.data.datasets.forEach((d) => d.data.shift());
        }
        grafica.update('none');
    }

    function pintar(d) {
        $('ultima-lectura').textContent = `Última lectura: ${d.hora}`;
        $('cpu-uso').textContent = d.cpu.total.toFixed(1) + ' %';
        $('carga').textContent = d.carga.map((c) => c.toFixed(2)).join(' · ');
        $('uptime').textContent = d.uptime.texto;
        agregar(graficaCpu, d.hora, [d.cpu.total, ...d.cpu.por_nucleo]);

        const m = d.memoria;
        const pct = (x) => (m.total_kb ? (100 * x) / m.total_kb : 0);
        $('ram-total').textContent = kb(m.total_kb);
        $('ram-pct').textContent = m.pct.toFixed(1) + ' % usada';
        $('ram-usada').style.width = pct(m.usada_kb) + '%';
        $('ram-cache').style.width = pct(m.cache_kb) + '%';
        $('ram-usada-mb').textContent = kb(m.usada_kb);
        $('ram-cache-mb').textContent = kb(m.cache_kb);
        $('ram-libre-mb').textContent = kb(m.libre_kb);
        $('ram-disponible').textContent = kb(m.disponible_kb);
        agregar(graficaMem, d.hora, [m.pct]);

        const s = d.swap;
        $('swap-total').textContent = s.total_kb ? kb(s.total_kb) : 'sin swap';
        $('swap-pct').textContent = s.total_kb ? s.pct.toFixed(1) + ' %' : '—';
        $('swap-usada').style.width = s.pct + '%';

        $('top-cpu').innerHTML = filas(d.top_cpu, (p) => p.cpu.toFixed(1) + ' %');
        $('top-memoria').innerHTML = filas(d.top_memoria, (p) => `${kb(p.mem_kb)} <span class="text-secondary">(${p.mem_pct} %)</span>`);
    }

    function filas(lista, valor) {
        if (!lista.length) return '<tr><td colspan="4" class="sm-vacio">No hay datos para mostrar.</td></tr>';
        return lista.map((p) => `<tr><td class="sm-num">${p.pid}</td><td class="sm-mono">${esc(p.comando)}</td>`
            + `<td>${esc(p.usuario)}</td><td class="text-end sm-num">${valor(p)}</td></tr>`).join('');
    }

    // Ciclo de actualización
    let temporizador = null;
    let pausado = false;

    async function actualizar() {
        try {
            pintar(await getJson(url));
        } catch (e) {
            $('ultima-lectura').textContent = 'Error al leer: ' + e.message;
        }
        if (!pausado) temporizador = setTimeout(actualizar, Number($('intervalo').value));
    }

    $('intervalo').addEventListener('change', () => {
        clearTimeout(temporizador);
        if (!pausado) actualizar();
    });

    $('pausar').addEventListener('click', (ev) => {
        pausado = !pausado;
        ev.currentTarget.innerHTML = pausado ? '<i class="bi bi-play-fill"></i> Reanudar' : '<i class="bi bi-pause-fill"></i> Pausar';
        clearTimeout(temporizador);
        if (!pausado) actualizar();
    });

    actualizar();
})();

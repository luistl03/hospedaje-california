// Ubicación destino: resources/js/predicciones/index.js
// ============================================================
//  PREDICCIONES — index.js
//  Pinta las proyecciones (línea histórico sólido + punteado)
//  y el ranking de sugerencias (barras horizontales).
//  Los selects de "histórico" y "proyección" recalculan vía fetch,
//  igual que el filtro de rango en Reportes.
// ============================================================

const PALETA_PRED = ['#C88838', '#001441', '#6f42c1', '#198754', '#dc3545', '#0d6efd'];

const CONFIG_INDICADORES = [
    { key: 'ingresos',          canvas: 'chartIngresosPred',    sufijo: 'S/', color: 0 },
    { key: 'reservas',          canvas: 'chartReservasPred',    sufijo: '',   color: 1 },
    { key: 'ocupacion',         canvas: 'chartOcupacionPred',   sufijo: '%',  color: 2 },
    { key: 'ticket_promedio',   canvas: 'chartTicketPred',      sufijo: 'S/', color: 3 },
    { key: 'tasa_cancelacion',  canvas: 'chartCancelacionPred', sufijo: '%',  color: 4 },
    { key: 'pct_horas',         canvas: 'chartHorasPred',       sufijo: '%',  color: 5 },
];

let chartsPrediccion = {};
let chartSugerencias = null;

function formatoValor(valor, sufijo) {
    if (sufijo === 'S/') return `S/ ${Number(valor).toFixed(2)}`;
    if (sufijo === '%')  return `${Number(valor).toFixed(1)}%`;
    return Number(valor).toString();
}

// Construye dos series alineadas al mismo eje de labels:
// "Histórico" (nulls en el tramo futuro) y "Proyección" (nulls en el tramo pasado,
// empalmando en el último punto histórico para que la línea no quede cortada).
function construirDatasetsLinea(historico, proyeccion) {
    const labels = [...historico.map(h => h.periodo), ...proyeccion.map(p => p.periodo)];

    const datosHistorico = [
        ...historico.map(h => h.valor),
        ...proyeccion.map(() => null),
    ];

    const datosProyeccion = [
        ...historico.map(() => null),
        ...(historico.length ? [historico[historico.length - 1].valor] : []),
        ...proyeccion.slice(historico.length ? 1 : 0).map(p => p.valor),
    ];

    return { labels, datosHistorico, datosProyeccion };
}

function pintarLinea(cfg, data) {
    const el = document.getElementById(cfg.canvas);
    if (!el || !data) return;

    const { labels, datosHistorico, datosProyeccion } = construirDatasetsLinea(data.historico, data.proyeccion);
    const color = PALETA_PRED[cfg.color];

    if (chartsPrediccion[cfg.key]) chartsPrediccion[cfg.key].destroy();

    chartsPrediccion[cfg.key] = new Chart(el.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Histórico',
                    data: datosHistorico,
                    borderColor: color,
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    pointRadius: 2,
                    spanGaps: false,
                },
                {
                    label: 'Proyección',
                    data: datosProyeccion,
                    borderColor: color,
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    tension: 0.3,
                    pointRadius: 2,
                    spanGaps: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${formatoValor(ctx.raw, cfg.sufijo)}`,
                    },
                },
            },
            scales: { y: { beginAtZero: true } },
        },
    });
}

function pintarSugerencias(sugerencias) {
    const el = document.getElementById('chartSugerenciasPred');
    const contadorEl = document.getElementById('sugerenciasTotalRegistros');
    if (contadorEl) contadorEl.textContent = sugerencias.total_registros;

    if (!el) return;

    if (sugerencias.ranking.length === 0) {
        el.parentElement.innerHTML = '<p class="evento-vacio">No hay sugerencias registradas todavía.</p>';
        return;
    }

    const labels = sugerencias.ranking.map(r => r.termino);
    const valores = sugerencias.ranking.map(r => r.veces);

    if (chartSugerencias) chartSugerencias.destroy();

    chartSugerencias = new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Menciones',
                data: valores,
                backgroundColor: '#C88838',
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
        },
    });
}

// El service devuelve, por cada indicador, un campo "modelo" con el texto
// del modelo realmente usado (varía según cuántos meses de histórico haya).
// Si CUALQUIER indicador cayó a "sin estacionalidad" (histórico < 12 meses),
// se muestra el aviso explicando por qué la proyección puede verse más plana.
function huboFallbackSinEstacionalidad(data) {
    return CONFIG_INDICADORES.some(cfg => {
        const modelo = data[cfg.key]?.modelo ?? '';
        return modelo.includes('histórico insuficiente');
    });
}

function pintarPredicciones(data) {
    CONFIG_INDICADORES.forEach(cfg => pintarLinea(cfg, data[cfg.key]));
    pintarSugerencias(data.sugerencias);

    const metaEl = document.getElementById('prediccionRango');
    if (metaEl) {
        metaEl.textContent =
            `Entrenado con datos desde ${data.meta.desde} ` +
            `(${data.meta.meses_historico_reales} de ${data.meta.meses_historico_solicitados} meses solicitados con datos) ` +
            `· proyección a ${data.meta.meses_proyeccion} meses`;
    }

    const sinDatosEl = document.getElementById('prediccionSinDatos');
    if (sinDatosEl) {
        sinDatosEl.style.display = data.meta.hay_datos ? 'none' : 'block';
    }

    const notaEstacionalEl = document.getElementById('prediccionNotaEstacional');
    if (notaEstacionalEl) {
        notaEstacionalEl.style.display = huboFallbackSinEstacionalidad(data) ? 'block' : 'none';
    }
}

// Lee los selects y recalcula. Si el usuario cambia un select, este es el único punto de entrada.
window.recalcularPredicciones = function () {
    const mesesHistorico = document.getElementById('predMesesHistorico')?.value ?? 24;
    const mesesProyeccion = document.getElementById('predMesesProyeccion')?.value ?? 6;

    const params = new URLSearchParams({
        meses_historico: mesesHistorico,
        meses_proyeccion: mesesProyeccion,
    });

    fetch(`/predicciones/datos?${params}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(pintarPredicciones)
        .catch(err => console.error('Error cargando predicciones:', err));
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.__prediccionesIniciales) {
        pintarPredicciones(window.__prediccionesIniciales);
    } else {
        window.recalcularPredicciones();
    }

    // Recalcula automáticamente al cambiar cualquiera de los dos selects
    document.getElementById('predMesesHistorico')?.addEventListener('change', window.recalcularPredicciones);
    document.getElementById('predMesesProyeccion')?.addEventListener('change', window.recalcularPredicciones);
});
// ============================================================
//  REPORTES — index.js
//  Filtro de rango + KPIs + gráficos (Chart.js) + rankings.
// ============================================================

let chartIngresos = null;
let chartMetodos  = null;

const PALETA = ['#C88838', '#001441', '#6f42c1', '#198754', '#dc3545', '#0d6efd', '#fd7e14'];

function formatoMoneda(valor) {
    return `S/ ${Number(valor).toFixed(2)}`;
}

// ── Presets rápidos de rango ──
window.aplicarPreset = function (dias) {
    const hasta = new Date();
    const desde = new Date();
    desde.setDate(desde.getDate() - (dias - 1));
    document.getElementById('repDesde').value = desde.toISOString().slice(0, 10);
    document.getElementById('repHasta').value = hasta.toISOString().slice(0, 10);
    window.cargarReportes();
};

window.aplicarPresetMes = function () {
    const hoy = new Date();
    const desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    document.getElementById('repDesde').value = desde.toISOString().slice(0, 10);
    document.getElementById('repHasta').value = hoy.toISOString().slice(0, 10);
    window.cargarReportes();
};

// ── Validación de rango de fechas ──
function validarRangoFechas(desde, hasta) {
    const errorEl = document.getElementById('repFechaError');
    const hoy = new Date().toISOString().slice(0, 10);

    let mensaje = '';

    if (!desde || !hasta) {
        mensaje = 'Debes seleccionar ambas fechas.';
    } else if (desde > hoy || hasta > hoy) {
        mensaje = 'No se pueden seleccionar fechas futuras.';
    } else if (desde > hasta) {
        mensaje = 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".';
    }

    if (mensaje) {
        errorEl.textContent = mensaje;
        errorEl.style.display = 'block';
        return false;
    }

    errorEl.style.display = 'none';
    return true;
}

// ── Fetch + repintado completo del panel ──
window.cargarReportes = function () {
    const desde = document.getElementById('repDesde').value;
    const hasta = document.getElementById('repHasta').value;

    if (!validarRangoFechas(desde, hasta)) {
        return; // no dispara el fetch si el rango es inválido
    }

    const params = new URLSearchParams({ desde, hasta });

    fetch(`/reportes/datos?${params}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(pintarMetricas)
        .catch(err => console.error('Error cargando reportes:', err));
};

// ── Pinta KPIs, gráficos y tablas a partir de la respuesta ──
function pintarMetricas(data) {
    // KPIs
    document.getElementById('kpiIngresos').textContent    = formatoMoneda(data.kpis.ingresos_netos);
    document.getElementById('kpiReservas').textContent    = data.kpis.reservas_nuevas;
    document.getElementById('kpiOcupacion').textContent   = `${data.kpis.ocupacion_promedio}%`;
    document.getElementById('kpiTicket').textContent      = formatoMoneda(data.kpis.ticket_promedio);
    document.getElementById('kpiCancelacion').textContent = `${data.kpis.tasa_cancelacion}%`;

    // Gráfico de línea — ingresos por día
    const ctxIngresos = document.getElementById('chartIngresos').getContext('2d');
    const labelsIngresos = data.ingresos_por_dia.map(d => d.fecha);
    const valoresIngresos = data.ingresos_por_dia.map(d => d.monto);

    if (chartIngresos) chartIngresos.destroy();
    chartIngresos = new Chart(ctxIngresos, {
        type: 'line',
        data: {
            labels: labelsIngresos,
            datasets: [{
                label: 'Ingresos (S/)',
                data: valoresIngresos,
                borderColor: '#C88838',
                backgroundColor: 'rgba(200,136,56,0.12)',
                tension: 0.3,
                fill: true,
                pointRadius: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });

    // Gráfico de dona — métodos de pago
    const ctxMetodos = document.getElementById('chartMetodos').getContext('2d');
    const labelsMetodos = data.ingresos_por_metodo.map(m => m.metodo.charAt(0).toUpperCase() + m.metodo.slice(1));
    const valoresMetodos = data.ingresos_por_metodo.map(m => parseFloat(m.monto));

    if (chartMetodos) chartMetodos.destroy();
    chartMetodos = new Chart(ctxMetodos, {
        type: 'doughnut',
        data: {
            labels: labelsMetodos,
            datasets: [{ data: valoresMetodos, backgroundColor: PALETA }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
        },
    });

    // Tabla: top habitaciones
    const habsEl = document.getElementById('tablaTopHabitaciones');
    habsEl.innerHTML = data.top_habitaciones.length === 0
        ? '<p class="evento-vacio">Sin datos en este rango.</p>'
        : data.top_habitaciones.map(h => `
            <div class="ver-fila">
                <span class="ver-fila-label">
                    N° ${h.numero} <span class="ver-tag">${h.tipo}</span>
                </span>
                <span class="ver-fila-valor">
                    ${formatoMoneda(h.ingresos)}
                    <span class="ver-fila-valor-secundario">(${h.reservas} reservas)</span>
                </span>
            </div>`).join('');

    // Tabla: ranking por tipo
    const tiposEl = document.getElementById('tablaTopTipos');
    tiposEl.innerHTML = data.top_tipos.length === 0
        ? '<p class="evento-vacio">Sin datos en este rango.</p>'
        : data.top_tipos.map(t => `
            <div class="ver-fila">
                <span class="ver-fila-label">${t.tipo}</span>
                <span class="ver-fila-valor">
                    ${formatoMoneda(t.ingresos)}
                    <span class="ver-fila-valor-secundario">(${t.reservas} reservas)</span>
                </span>
            </div>`).join('');

    // Tabla: huéspedes frecuentes
    const huespedesEl = document.getElementById('tablaTopHuespedes');
    huespedesEl.innerHTML = data.top_huespedes.length === 0
        ? '<p class="evento-vacio">Sin datos en este rango.</p>'
        : data.top_huespedes.map(h => `
            <div class="ver-fila">
                <span class="ver-fila-label">
                    ${h.nombre} <span class="ver-tag">${h.num_doc}</span>
                </span>
                <span class="ver-fila-valor">
                    ${formatoMoneda(h.gasto)}
                    <span class="ver-fila-valor-secundario">(${h.reservas} reservas)</span>
                </span>
            </div>`).join('');
}

// ── Carga inicial: usa los datos ya renderizados por el servidor (sin esperar fetch) ──
document.addEventListener('DOMContentLoaded', () => {
    if (window.__metricasIniciales) {
        pintarMetricas(window.__metricasIniciales);
    } else {
        window.cargarReportes();
    }
});
<x-app-layout>

    <div class="pagina-contenedor">

        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Reportes</h1>
        </div>

        {{-- Filtro de rango de fechas --}}
        <div class="filtros-barra">
            <div class="filtro-grupo">
                <label>Desde</label>
                <div class="campo-input">
                    <i class="bi bi-calendar campo-icono"></i>
                    <input type="date" id="repDesde" value="{{ $metricas['rango']['desde'] }}" max="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="filtro-grupo">
                <label>Hasta</label>
                <div class="campo-input">
                    <i class="bi bi-calendar-check campo-icono"></i>
                    <input type="date" id="repHasta" value="{{ $metricas['rango']['hasta'] }}" max="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="filtros-acciones">
                <button class="btn-secundario" onclick="window.aplicarPreset(7)">7 días</button>
                <button class="btn-secundario" onclick="window.aplicarPreset(30)">30 días</button>
                <button class="btn-secundario" onclick="window.aplicarPresetMes()">Este mes</button>
                <button class="btn-primario" onclick="window.cargarReportes()">
                    <i class="bi bi-search"></i> Aplicar
                </button>
            </div>
        </div>
        <span id="repFechaError" class="campo-error mb-12" style="display:none;"></span>

        {{-- KPIs --}}
        <div class="kpi-grid" id="kpiGrid">
            <div class="kpi-card kpi-verde">
                <div class="kpi-label"><i class="bi bi-cash-coin"></i> Ingresos netos</div>
                <div class="kpi-numero" id="kpiIngresos">S/ 0.00</div>
            </div>
            <div class="kpi-card kpi-azul">
                <div class="kpi-label"><i class="bi bi-calendar-plus"></i> Reservas nuevas</div>
                <div class="kpi-numero" id="kpiReservas">0</div>
            </div>
            <div class="kpi-card kpi-morado">
                <div class="kpi-label"><i class="bi bi-door-open"></i> Ocupación promedio</div>
                <div class="kpi-numero" id="kpiOcupacion">0%</div>
            </div>
            <div class="kpi-card kpi-dorado">
                <div class="kpi-label"><i class="bi bi-receipt"></i> Ticket promedio</div>
                <div class="kpi-numero" id="kpiTicket">S/ 0.00</div>
            </div>
            <div class="kpi-card kpi-rojo">
                <div class="kpi-label"><i class="bi bi-x-circle"></i> Tasa de cancelación</div>
                <div class="kpi-numero" id="kpiCancelacion">0%</div>
            </div>
        </div>

        {{-- Gráficos --}}
        <div class="reportes-graficos-grid">
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-graph-up"></i> Ingresos por día</div>
                <div class="grafico-contenedor">
                    <canvas id="chartIngresos"></canvas>
                </div>
            </div>
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-pie-chart"></i> Métodos de pago</div>
                <div class="grafico-contenedor grafico-dona">
                    <canvas id="chartMetodos"></canvas>
                </div>
            </div>
        </div>

        {{-- Rankings --}}
        <div class="reportes-tablas-grid">

            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-trophy"></i> Top habitaciones</div>
                <div class="dashboard-panel-body">
                    <div id="tablaTopHabitaciones"></div>
                </div>
            </div>

            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-bar-chart-steps"></i> Ranking por tipo</div>
                <div class="dashboard-panel-body">
                    <div id="tablaTopTipos"></div>
                </div>
            </div>

            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-person-heart"></i> Huéspedes frecuentes</div>
                <div class="dashboard-panel-body">
                    <div id="tablaTopHuespedes"></div>
                </div>
            </div>

            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-wallet2"></i> Ingresos por tipo de pago</div>
                <div class="dashboard-panel-body">
                    <div id="tablaTipoPago"></div>
                </div>
            </div>

            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-hourglass-split"></i> Distribución de estadía</div>
                <div class="dashboard-panel-body">
                    <div id="tablaDistribucionEstadia"></div>
                </div>
            </div>

            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-chat-square-text"></i> Sugerencias registradas</div>
                <div class="dashboard-panel-body">
                    <div id="tablaSugerencias"></div>
                </div>
            </div>

        </div>

    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            // Datos iniciales renderizados por el servidor, para el primer pintado sin esperar el fetch
            window.__metricasIniciales = @json($metricas);
        </script>
        @vite('resources/js/reportes/index.js')
    @endpush

</x-app-layout>
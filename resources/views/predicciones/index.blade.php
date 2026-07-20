<!-- Ubicación destino: resources/views/predicciones/index.blade.php -->
<x-app-layout>

    <div class="pagina-contenedor">

        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Analítica Predictiva</h1>
        </div>

        {{-- Filtro: cuántos meses de histórico entrenan el modelo y cuántos se proyectan --}}
        <div class="filtros-barra">
            <div class="filtro-grupo">
                <label>Histórico a usar</label>
                <div class="campo-input">
                    <i class="bi bi-clock-history campo-icono"></i>
                    <select id="predMesesHistorico">
                        @foreach ($opcionesHistorico as $opcion)
                            <option value="{{ $opcion }}" @selected($opcion === $mesesHistorico)>
                                Últimos {{ $opcion }} meses
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="filtro-grupo">
                <label>Proyectar a futuro</label>
                <div class="campo-input">
                    <i class="bi bi-graph-up-arrow campo-icono"></i>
                    <select id="predMesesProyeccion">
                        @foreach ($opcionesProyeccion as $opcion)
                            <option value="{{ $opcion }}" @selected($opcion === $mesesProyeccion)>
                                {{ $opcion }} {{ $opcion === 1 ? 'mes' : 'meses' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="filtros-acciones">
                <button class="btn-primario" onclick="window.recalcularPredicciones()">
                    <i class="bi bi-arrow-clockwise"></i> Recalcular
                </button>
            </div>
        </div>

        <p class="texto-ayuda" id="prediccionRango"></p>
        <p class="campo-error mb-12" id="prediccionSinDatos" style="display:none;">
            Todavía no hay suficientes pagos/reservas registrados para entrenar el modelo. Las proyecciones se irán
            ajustando a medida que se registre actividad.
        </p>
        <p class="texto-ayuda" id="prediccionNotaEstacional" style="display:none;">
            Con menos de 12 meses de histórico seleccionados, la proyección usa solo tendencia (sin estacionalidad),
            porque no hay un ciclo anual completo para estimarla de forma confiable.
        </p>

        {{-- Series con proyección --}}
        <div class="reportes-graficos-grid">
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-graph-up-arrow"></i> Ingresos (proyección estacional)</div>
                <div class="grafico-contenedor"><canvas id="chartIngresosPred"></canvas></div>
            </div>
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-calendar-plus"></i> Reservas (proyección estacional)</div>
                <div class="grafico-contenedor"><canvas id="chartReservasPred"></canvas></div>
            </div>
        </div>

        <div class="reportes-graficos-grid">
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-door-open"></i> Ocupación (proyección estacional)</div>
                <div class="grafico-contenedor"><canvas id="chartOcupacionPred"></canvas></div>
            </div>
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-receipt"></i> Ticket promedio (proyección estacional)</div>
                <div class="grafico-contenedor"><canvas id="chartTicketPred"></canvas></div>
            </div>
        </div>

        <div class="reportes-graficos-grid">
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-x-circle"></i> Tasa de cancelación (proyección estacional)</div>
                <div class="grafico-contenedor"><canvas id="chartCancelacionPred"></canvas></div>
            </div>
            <div class="tabla-contenedor dashboard-panel">
                <div class="dashboard-panel-titulo"><i class="bi bi-hourglass-split"></i> % Estadía por horas (proyección estacional)</div>
                <div class="grafico-contenedor"><canvas id="chartHorasPred"></canvas></div>
            </div>
        </div>

        {{-- Sugerencias: minería de texto (no depende del filtro de meses, usa todo el histórico) --}}
        <div class="tabla-contenedor dashboard-panel">
            <div class="dashboard-panel-titulo">
                <i class="bi bi-chat-square-text"></i> Términos más mencionados en sugerencias
                <span class="ver-tag" id="sugerenciasTotalRegistros">0</span>
            </div>
            <div class="dashboard-panel-body">
                <div class="grafico-contenedor" style="height: 420px;">
                    <canvas id="chartSugerenciasPred"></canvas>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            // Datos iniciales renderizados por el servidor, para el primer pintado sin esperar el fetch
            window.__prediccionesIniciales = @json($predicciones);
        </script>
        @vite('resources/js/predicciones/index.js')
    @endpush

</x-app-layout>
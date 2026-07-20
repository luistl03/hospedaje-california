<x-app-layout>

    <div class="pagina-contenedor">

        {{-- ENCABEZADO --}}
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Inicio</h1>
        </div>

        {{-- CUERPO: Mapa de habitaciones (izq) + columna derecha (indicadores + eventos) --}}
        <div class="dashboard-grid">

            {{-- ── MAPA DE HABITACIONES ── --}}
            <div class="tabla-contenedor dashboard-panel" id="mapaHabitacionesDashboard">
                <div class="dashboard-panel-titulo">
                    <i class="bi bi-grid-3x3-gap"></i> Mapa de Habitaciones
                </div>
                <div class="dashboard-panel-body">
                    @forelse($mapaPisos as $piso)
                        <div class="piso-seccion">
                            <div class="piso-label">Piso {{ $piso['piso'] }}</div>
                            <div class="piso-habitaciones">
                                @foreach($piso['habitaciones'] as $hab)
                                    <div class="hab-card hab-{{ $hab['estado'] }}"
                                        @if($hab['huesped'])
                                            title="{{ $hab['huesped'] }} — sale {{ $hab['fecha_salida'] }}"
                                        @endif>
                                        <div class="hab-numero">N° {{ $hab['numero'] }}</div>
                                        <div class="hab-tipo">{{ $hab['tipo_nombre'] }}</div>
                                        @if($hab['huesped'])
                                            <div class="hab-huesped">
                                                <i class="bi bi-person"></i> {{ $hab['huesped'] }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="paso2-estado">
                            <i class="bi bi-door-closed"></i>
                            No hay habitaciones registradas.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUMNA DERECHA: indicadores + eventos ── --}}
            <div class="dashboard-columna-derecha">

                {{-- INDICADORES --}}
                <div class="indicadores-grid">
                    <div class="indicador-card indicador-disponible">
                        <div class="indicador-encabezado">
                            <div class="indicador-icono"><i class="bi bi-door-open"></i></div>
                            <div class="indicador-label">Disponibles</div>
                        </div>
                        <div class="indicador-numero">{{ $indicadores['disponible'] }}</div>
                    </div>
                    <div class="indicador-card indicador-reservada">
                        <div class="indicador-encabezado">
                            <div class="indicador-icono"><i class="bi bi-bookmark-check"></i></div>
                            <div class="indicador-label">Reservadas</div>
                        </div>
                        <div class="indicador-numero">{{ $indicadores['reservada'] }}</div>
                    </div>
                    <div class="indicador-card indicador-ocupada">
                        <div class="indicador-encabezado">
                            <div class="indicador-icono"><i class="bi bi-person-fill"></i></div>
                            <div class="indicador-label">Ocupadas</div>
                        </div>
                        <div class="indicador-numero">{{ $indicadores['ocupada'] }}</div>
                    </div>
                    <div class="indicador-card indicador-limpieza">
                        <div class="indicador-encabezado">
                            <div class="indicador-icono"><i class="bi bi-stars"></i></div>
                            <div class="indicador-label">En limpieza</div>
                        </div>
                        <div class="indicador-numero">{{ $indicadores['limpieza'] }}</div>
                    </div>
                    <div class="indicador-card indicador-mantenimiento">
                        <div class="indicador-encabezado">
                            <div class="indicador-icono"><i class="bi bi-wrench"></i></div>
                            <div class="indicador-label">Mantenimiento</div>
                        </div>
                        <div class="indicador-numero">{{ $indicadores['mantenimiento'] }}</div>
                    </div>
                </div>

                {{-- EVENTOS DEL DÍA --}}
                <div class="tabla-contenedor dashboard-panel">
                    <div class="dashboard-panel-titulo">
                        <i class="bi bi-bell"></i> Eventos de Hoy
                    </div>
                    <div class="dashboard-panel-body">

                        <div class="evento-seccion">
                            <div class="evento-seccion-titulo evento-checkin">
                                <span><i class="bi bi-box-arrow-in-right"></i> Check-ins de hoy</span>
                                <span class="badge-contador-dorado">{{ $checkinsHoy->count() }}</span>
                            </div>
                            @forelse($checkinsHoy as $c)
                                <div class="ver-fila">
                                    <span class="ver-fila-label">
                                        {{ $c['huesped'] }}
                                        <span class="ver-tag">Hab. {{ $c['habitaciones'] }}</span>
                                        @if($c['atrasado'])
                                            <span class="ver-tag ver-tag-alerta">Atrasado</span>
                                        @endif
                                    </span>
                                    <span class="ver-fila-valor {{ $c['saldo'] > 0 ? 'ver-saldo-positivo' : 'ver-saldo-cero' }}">
                                        {{ $c['hora'] }}
                                        @if($c['saldo'] > 0)
                                            · S/ {{ number_format($c['saldo'], 2) }} pendiente
                                        @endif
                                    </span>
                                </div>
                            @empty
                                <p class="evento-vacio">No hay check-ins programados para hoy.</p>
                            @endforelse
                        </div>

                        <div class="evento-seccion">
                            <div class="evento-seccion-titulo evento-checkout">
                                <span><i class="bi bi-box-arrow-right"></i> Check-outs de hoy</span>
                                <span class="badge-contador-dorado">{{ $checkoutsHoy->count() }}</span>
                            </div>
                            @forelse($checkoutsHoy as $c)
                                <div class="ver-fila">
                                    <span class="ver-fila-label">
                                        {{ $c['huesped'] }}
                                        <span class="ver-tag">Hab. {{ $c['habitaciones'] }}</span>
                                        @if($c['atrasado'])
                                            <span class="ver-tag ver-tag-advertencia">Demorado</span>
                                        @endif
                                    </span>
                                    <span class="ver-fila-valor">{{ $c['hora'] }}</span>
                                </div>
                            @empty
                                <p class="evento-vacio">No hay check-outs programados para hoy.</p>
                            @endforelse
                        </div>

                        <div class="evento-seccion">
                            <div class="evento-seccion-titulo evento-pago">
                                <span><i class="bi bi-cash-coin"></i> Reservas con saldo pendiente</span>
                                <span class="badge-contador-dorado">{{ $pagosPendientes->count() }}</span>
                            </div>
                            @forelse($pagosPendientes as $p)
                                <div class="ver-fila">
                                    <span class="ver-fila-label">
                                        {{ $p['huesped'] }}
                                        <span class="ver-tag">Hab. {{ $p['habitaciones'] }}</span>
                                    </span>
                                    <span class="ver-fila-valor ver-saldo-positivo">
                                        S/ {{ number_format($p['saldo'], 2) }}
                                        <span class="ver-fila-valor-secundario">
                                            (entra {{ $p['fecha_entrada'] }})
                                        </span>
                                    </span>
                                </div>
                            @empty
                                <p class="evento-vacio">No hay saldos pendientes.</p>
                            @endforelse
                        </div>

                        <div class="evento-seccion">
                            <div class="evento-seccion-titulo evento-sugerencia">
                                <span><i class="bi bi-chat-square-text"></i> Sugerencias de hoy</span>
                                <span class="badge-contador-dorado">{{ $sugerenciasHoy->count() }}</span>
                            </div>
                            @forelse($sugerenciasHoy as $s)
                                <div class="ver-extension">
                                    <div class="ver-extension-header">
                                        <span>
                                            {{ $s['nombre'] ?? $s['num_doc'] }}
                                            <span class="ver-tag">{{ $s['num_doc'] }}</span>
                                        </span>
                                        <span>{{ $s['hora'] }}</span>
                                    </div>
                                    <div class="ver-extension-habs">
                                        {{ $s['comentario'] }}
                                    </div>
                                </div>
                            @empty
                                <p class="evento-vacio">No se registraron sugerencias hoy.</p>
                            @endforelse
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>

</x-app-layout>
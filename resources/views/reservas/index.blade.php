<x-app-layout>

    <div class="pagina-contenedor">

        <!-- ENCABEZADO -->
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Reservas</h1>
            <button class="btn-primario" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="bi bi-plus-lg"></i> Nueva Reserva
            </button>
        </div>

        <!-- ALERTAS -->
        @if(session('error'))
            <div class="login-error mb-3">
                <i class="bi bi-exclamation-circle"></i>
                {{ session('error') }}
            </div>
        @endif

        @if(session('exito'))
            <div class="alerta-exito mb-3">
                <i class="bi bi-check-circle"></i>
                {{ session('exito') }}
            </div>
        @endif

        <!-- FILTROS -->
        <div class="filtros-barra">

            <div class="filtro-grupo">
                <label>Estado</label>
                <div class="campo-select">
                    <i class="bi bi-circle campo-icono"></i>
                    <select id="filtroEstado">
                        <option value="">Todos</option>
                        @foreach($estadosReserva as $estado)
                            <option value="{{ $estado->id }}">{{ ucfirst($estado->nombre) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Fecha Entrada</label>
                <div class="campo-input">
                    <i class="bi bi-calendar campo-icono"></i>
                    <input type="date" id="filtroFechaEntrada">
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Huésped / Documento</label>
                <div class="campo-input">
                    <i class="bi bi-person campo-icono"></i>
                    <input type="text" id="filtroHuesped" placeholder="Nombre o documento...">
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Habitación</label>
                <div class="campo-input">
                    <i class="bi bi-door-open campo-icono"></i>
                    <input type="number" id="filtroHabitacion" placeholder="Nº hab." min="1">
                </div>
            </div>

            <div class="filtros-acciones">
                <button class="btn-primario" onclick="window.buscarReservas()">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <button class="btn-secundario" onclick="window.limpiarFiltros()">
                    <i class="bi bi-x-lg"></i> Limpiar
                </button>
            </div>

        </div>

        <!-- TABLA RESERVAS -->
        <div class="tabla-contenedor">
            <table class="tabla" id="tablaReservas">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Huéspedes</th>
                        <th>Habitaciones</th>
                        <th>Tipo</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div id="paginacion" class="paginacion-contenedor"></div>

    </div>

    <!-- MODAL CREAR RESERVA -->
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-calendar-plus"></i> Nueva Reserva</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('reservas.store') }}" id="formCrear">
                    @csrf
                    <div class="modal-body">

                        <!-- INDICADOR DE PASOS -->
                        <div class="pasos-indicadores">
                            @foreach(['Datos', 'Habitaciones', 'Huéspedes', 'Pagos'] as $i => $paso)
                                <div class="paso-indicador {{ $i === 0 ? 'paso-activo' : '' }}"
                                    id="indicador{{ $i + 1 }}">
                                    <span class="paso-numero">{{ $i + 1 }}</span>
                                    <span class="paso-label">{{ $paso }}</span>
                                </div>
                            @endforeach
                        </div>

                        <!-- PASO 1: DATOS GENERALES -->
                        <div id="paso1">

                            <div class="campo-grupo">
                                <label>Tipo de Estadía</label>
                                <div class="campo-select">
                                    <i class="bi bi-clock campo-icono"></i>
                                    <select name="tipo_estadia_id" id="tipoEstadiaId"
                                        onchange="window.actualizarPaso1()" required>
                                        <option value="">Seleccionar...</option>
                                        @foreach($tiposEstadia as $tipo)
                                            <option value="{{ $tipo->id }}"
                                                data-nombre="{{ $tipo->nombre }}">
                                                {{ ucfirst($tipo->nombre) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="fechas-grid">
                                <div class="campo-grupo">
                                    <label>Fecha y Hora de Entrada</label>
                                    <div class="campo-input">
                                        <i class="bi bi-calendar campo-icono"></i>
                                        <input type="datetime-local" name="fecha_entrada"
                                            id="fechaEntrada" onchange="window.validarEntrada()"
                                            required>
                                    </div>
                                </div>
                                <div class="campo-grupo">
                                    <label>Fecha y Hora de Salida</label>
                                    <div class="campo-input">
                                        <i class="bi bi-calendar-check campo-icono"></i>
                                        <input type="datetime-local" name="fecha_salida"
                                            id="fechaSalida" onchange="window.validarSalida()"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <!-- AVISO FRANJA HORARIA -->
                            <div id="avisoFranja" class="aviso-franja" style="display:none;">
                                <i class="bi bi-info-circle" style="margin-right:6px;"></i>
                                <span id="textoFranja"></span>
                            </div>

                            <div class="campo-grupo">
                                <label>
                                    Observación
                                    <span class="label-opcional">(opcional)</span>
                                </label>
                                <div class="campo-input">
                                    <i class="bi bi-chat-left-text campo-icono"></i>
                                    <input type="text" name="observacion"
                                        placeholder="Notas adicionales" maxlength="255">
                                </div>
                            </div>

                        </div>

                        <!-- PASO 2: HABITACIONES -->
                        <div id="paso2" style="display:none;">

                            <!-- CARGANDO -->
                            <div id="paso2Cargando" class="paso2-estado">
                                <i class="bi bi-arrow-repeat"></i>
                                Buscando habitaciones disponibles...
                            </div>

                            <!-- MAPA POR PISOS -->
                            <div id="paso2Mapa" style="display:none;"></div>

                            <!-- SIN RESULTADOS -->
                            <div id="paso2Vacio" class="paso2-estado" style="display:none;">
                                <i class="bi bi-door-closed"></i>
                                No hay habitaciones disponibles para el rango seleccionado.
                            </div>

                            <!-- RESUMEN SELECCIÓN -->
                            <div id="paso2Resumen" class="resumen-seleccion" style="display:none;">
                                <div class="resumen-seleccion-inner">
                                    <span id="paso2ResumenTexto" class="resumen-texto"></span>
                                    <strong id="paso2ResumenTotal" class="resumen-total"></strong>
                                </div>
                            </div>

                        </div>

                        <!-- PASO 3: HUÉSPEDES -->
                        <div id="paso3" style="display:none;">

                        <div id="paso3AvisoLimite" class="aviso-franja franja-horas"
                            style="display:none; margin-bottom:14px;">
                        </div>

                            <!-- BUSCADOR COMPACTO -->
                            <div class="paso3-buscador">
                                <div class="campo-grupo" style="max-width:150px;">
                                    <label>Tipo Documento</label>
                                    <div class="campo-select">
                                        <i class="bi bi-id-card campo-icono"></i>
                                        <select id="paso3TipoDoc">
                                            <option value="">Todos</option>
                                            @foreach($tiposDocumento as $td)
                                                <option value="{{ $td->id }}">{{ strtoupper($td->nombre) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="campo-grupo" style="max-width:150px;">
                                    <label>Nº Documento</label>
                                    <div class="campo-input">
                                        <i class="bi bi-123 campo-icono"></i>
                                        <input type="text" id="paso3NumDoc" placeholder="12345678" maxlength="20">
                                    </div>
                                </div>
                                <div class="campo-grupo">
                                    <label>Nombre</label>
                                    <div class="campo-input">
                                        <i class="bi bi-person campo-icono"></i>
                                        <input type="text" id="paso3Nombre" placeholder="Buscar por nombre..." maxlength="100">
                                    </div>
                                </div>
                                <div style="display:flex; gap:8px; padding-bottom:8px;">
                                    <button type="button" class="btn-primario" onclick="window.buscarHuesped()">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <button type="button" class="btn-secundario" onclick="window.limpiarBuscadorHuesped()">
                                        <i class="bi bi-x-lg"></i> Limpiar
                                    </button>
                                </div>
                            </div>

                            <!-- RESULTADOS -->
                            <div id="paso3Resultados" style="display:none;">
                                <div class="piso-label" style="margin-bottom:6px;">Resultados</div>
                                <div id="paso3ListaResultados" class="paso3-lista"></div>
                            </div>

                            <!-- SIN RESULTADOS -->
                            <div id="paso3Vacio" class="paso2-estado" style="display:none;">
                                <i class="bi bi-person-x"></i>
                                No se encontró ningún huésped con esos datos.
                            </div>

                            <!-- SELECCIONADOS -->
                            <div id="paso3Seleccionados" class="paso3-seleccionados" style="display:none;">
                                <div class="paso3-seleccionados-titulo">
                                    Huéspedes agregados
                                    <span id="paso3ContadorBadge" class="badge-contador-dorado"></span>
                                </div>
                                <div id="paso3ListaSeleccionados"></div>
                            </div>

                        </div>


                        <!-- PASO 4: PAGOS -->
                        <div id="paso4" style="display:none;">

                            <!-- DESGLOSE -->
                            <div id="paso4Desglose" style="margin-bottom:20px;">

                                <div class="piso-label" style="margin-bottom:10px;">Resumen de cobro</div>

                                <div id="paso4FilasDesglose"></div>

                                <!-- Separador total -->
                                <div style="display:flex; justify-content:space-between; align-items:center;
                                    border-top: 2px solid var(--azul-marino); margin-top:10px; padding-top:10px;">
                                    <span style="font-size:0.88rem; font-weight:700; color:var(--azul-marino);
                                        text-transform:uppercase; letter-spacing:0.5px;">Total</span>
                                    <strong id="paso4Total" style="font-size:1.1rem; color:var(--azul-marino);"></strong>
                                </div>

                                <!-- Aviso ocupación -->
                                <div id="paso4AvisoOcupacion" class="aviso-franja" style="display:none; margin-top:12px;"></div>

                            </div>

                            <!-- PAGO -->
                            <div class="campo-grupo">
                                <label>
                                    Monto a pagar
                                    <span id="paso4MinimoLabel" class="label-opcional"></span>
                                </label>
                                <div class="campo-input">
                                    <i class="bi bi-cash campo-icono"></i>
                                    <input type="number" id="paso4MontoPago" step="0.01" min="0"
                                        placeholder="0.00" oninput="window.validarMontoPago()">
                                </div>
                                <div id="paso4ErrorMonto" style="display:none; color:#dc3545;
                                    font-size:0.78rem; margin-top:4px;"></div>
                            </div>

                            <div class="campo-grupo">
                                <label>Método de Pago</label>
                                <div class="campo-select">
                                    <i class="bi bi-wallet2 campo-icono"></i>
                                    <select id="paso4MetodoId">
                                        <option value="">Seleccionar...</option>
                                        @foreach($metodosPago as $metodo)
                                            <option value="{{ $metodo->id }}">{{ ucfirst($metodo->nombre) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>
                    <div class="modal-footer modal-footer-reserva">
                        <button type="button" class="btn-secundario" id="btnAnterior"
                            style="display:none;" onclick="window.cambiarPaso(-1)">
                            <i class="bi bi-chevron-left"></i> Anterior
                        </button>
                        <div class="modal-footer-acciones">
                            <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="button" class="btn-primario" id="btnSiguiente"
                                onclick="window.cambiarPaso(1)">
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </button>
                            <button type="button" class="btn-primario" id="btnConfirmar"
                                style="display:none;" onclick="window.confirmarReserva()">
                                <i class="bi bi-check-lg"></i> Confirmar Reserva
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL VER DETALLE -->
    <div class="modal fade" id="modalVer" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-eye"></i>
                        Detalle de Reserva <span id="verReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body" id="verModalBody">

                    <!-- CARGANDO -->
                    <div id="verCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información...
                    </div>

                    <!-- CONTENIDO -->
                    <div id="verContenido" style="display:none;">

                        <!-- FILA SUPERIOR: datos generales + estado -->
                        <div class="ver-cabecera">
                            <div class="ver-cabecera-datos">
                                <div class="ver-dato">
                                    <span class="ver-dato-label">Tipo estadía</span>
                                    <span class="ver-dato-valor" id="verTipo"></span>
                                </div>
                                <div class="ver-dato">
                                    <span class="ver-dato-label">Entrada</span>
                                    <span class="ver-dato-valor" id="verEntrada"></span>
                                </div>
                                <div class="ver-dato">
                                    <span class="ver-dato-label">Salida</span>
                                    <span class="ver-dato-valor" id="verSalida"></span>
                                </div>
                                <div class="ver-dato">
                                    <span class="ver-dato-label">Registrado por</span>
                                    <span class="ver-dato-valor" id="verUsuario"></span>
                                </div>
                                <div class="ver-dato">
                                    <span class="ver-dato-label">Fecha registro</span>
                                    <span class="ver-dato-valor" id="verCreatedAt"></span>
                                </div>
                                <div class="ver-dato ver-dato-full">
                                    <span class="ver-dato-label">Observación</span>
                                    <span class="ver-dato-valor" id="verObservacion"></span>
                                </div>
                            </div>
                            <div class="ver-cabecera-estado">
                                <span id="verEstadoBadge" class="badge-estado"></span>
                            </div>
                        </div>

                        <!-- HABITACIONES -->
                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-door-open"></i> Habitaciones
                            </div>
                            <div id="verHabitaciones"></div>
                        </div>

                        <!-- HUÉSPEDES -->
                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-people"></i> Huéspedes
                            </div>
                            <div id="verHuespedes"></div>
                        </div>

                        <!-- PAGOS -->
                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-cash-coin"></i> Pagos
                            </div>
                            <div id="verPagos"></div>
                        </div>

                        <!-- EXTENSIONES -->
                        <div class="ver-seccion" id="verSeccionExtensiones" style="display:none;">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-clock-history"></i> Extensiones
                            </div>
                            <div id="verExtensiones"></div>
                        </div>

                        <!-- SALDO -->
                        <div class="ver-saldo">
                            <div class="ver-saldo-fila">
                                <span>Total reserva</span>
                                <strong id="verMontoTotal"></strong>
                            </div>
                            <div class="ver-saldo-fila">
                                <span>Pagado</span>
                                <strong id="verMontoPagado" style="color:#198754;"></strong>
                            </div>
                            <div class="ver-saldo-fila ver-saldo-pendiente">
                                <span>Saldo pendiente</span>
                                <strong id="verSaldo"></strong>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL REGISTRAR PAGO -->
    <div class="modal fade" id="modalPago" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-cash-coin"></i>
                        Registrar Pago — Reserva <span id="pagoReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">

                    <!-- CARGANDO -->
                    <div id="pagoCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información...
                    </div>

                    <!-- CONTENIDO -->
                    <div id="pagoContenido" style="display:none;">

                        <!-- Resumen saldo -->
                        <div class="ver-saldo" style="margin-bottom:20px;">
                            <div class="ver-saldo-fila">
                                <span>Total reserva</span>
                                <strong id="pagoMontoTotal"></strong>
                            </div>
                            <div class="ver-saldo-fila">
                                <span>Pagado</span>
                                <strong id="pagoMontoPagado" style="color:#198754;"></strong>
                            </div>
                            <div class="ver-saldo-fila ver-saldo-pendiente">
                                <span>Saldo pendiente</span>
                                <strong id="pagoSaldo" style="color:#dc3545;"></strong>
                            </div>
                        </div>

                        <!-- Monto -->
                        <div class="campo-grupo">
                            <label>
                                Monto a pagar
                                <span id="pagoMaximoLabel" class="label-opcional"></span>
                            </label>
                            <div class="campo-input">
                                <i class="bi bi-cash campo-icono"></i>
                                <input type="number" id="pagoMonto" step="0.01" min="0.01"
                                    placeholder="0.00" oninput="window.validarMontoPagoModal()">
                            </div>
                            <div id="pagoErrorMonto" style="display:none; color:#dc3545;
                                font-size:0.78rem; margin-top:4px;"></div>
                        </div>

                        <!-- Método -->
                        <div class="campo-grupo">
                            <label>Método de Pago</label>
                            <div class="campo-select">
                                <i class="bi bi-wallet2 campo-icono"></i>
                                <select id="pagoMetodoId">
                                    <option value="">Seleccionar...</option>
                                    @foreach($metodosPago as $metodo)
                                        <option value="{{ $metodo->id }}">
                                            {{ ucfirst($metodo->nombre) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Aviso tipo pago resultante -->
                        <div id="pagoAvisoTipo" class="aviso-franja" style="display:none; margin-top:4px;"></div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="pagoBtnConfirmar"
                        style="display:none;" onclick="window.confirmarPago()">
                        <i class="bi bi-check-lg"></i> Confirmar Pago
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL CHECK-IN -->
    <div class="modal fade" id="modalCheckin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Check-in — Reserva <span id="checkinReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">

                    <div id="checkinCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Verificando disponibilidad...
                    </div>

                    <div id="checkinContenido" style="display:none;">

                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-door-open"></i> Habitaciones
                            </div>
                            <div id="checkinHabitaciones"></div>
                        </div>

                        <div id="checkinRecargo" class="aviso-franja" style="display:none; margin-top:12px;"></div>

                        <div id="checkinMetodoGrupo" class="campo-grupo" style="display:none; margin-top:16px;">
                            <label>Método de Pago del Recargo</label>
                            <div class="campo-select">
                                <i class="bi bi-wallet2 campo-icono"></i>
                                <select id="checkinMetodoId">
                                    <option value="">Seleccionar...</option>
                                    @foreach($metodosPago as $metodo)
                                        <option value="{{ $metodo->id }}">{{ ucfirst($metodo->nombre) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="checkinErrorMetodo" style="display:none; color:#dc3545;
                                font-size:0.78rem; margin-top:4px;">
                                Seleccione un método de pago para el recargo.
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="checkinBtnConfirmar"
                        style="display:none;" onclick="window.confirmarCheckin()">
                        <i class="bi bi-box-arrow-in-right"></i> Confirmar Check-in
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR FECHAS/TIPO -->
    <div class="modal fade" id="modalEditarFechas" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-calendar-event"></i>
                        Editar Fechas/Tipo — Reserva <span id="efReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">

                    <!-- CARGANDO -->
                    <div id="efCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información...
                    </div>

                    <!-- CONTENIDO -->
                    <div id="efContenido" style="display:none;">

                        <!-- Tipo estadía -->
                        <div class="campo-grupo">
                            <label>Tipo de Estadía</label>
                            <div class="campo-select">
                                <i class="bi bi-clock campo-icono"></i>
                                <select id="efTipoEstadiaId" onchange="window.efOnTipoChange()">
                                    @foreach($tiposEstadia as $tipo)
                                        <option value="{{ $tipo->id }}"
                                            data-nombre="{{ $tipo->nombre }}">
                                            {{ ucfirst($tipo->nombre) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Fechas -->
                        <div class="fechas-grid">
                            <div class="campo-grupo">
                                <label>Fecha y Hora de Entrada</label>
                                <div class="campo-input">
                                    <i class="bi bi-calendar campo-icono"></i>
                                    <input type="datetime-local" id="efFechaEntrada"
                                        onchange="window.efOnEntradaChange()">
                                </div>
                            </div>
                            <div class="campo-grupo">
                                <label>Fecha y Hora de Salida</label>
                                <div class="campo-input">
                                    <i class="bi bi-calendar-check campo-icono"></i>
                                    <input type="datetime-local" id="efFechaSalida"
                                        onchange="window.efOnSalidaChange()">
                                </div>
                            </div>
                        </div>

                        <!-- Aviso franja -->
                        <div id="efAvisoFranja" class="aviso-franja" style="display:none;"></div>

                        <!-- Observación -->
                        <div class="campo-grupo">
                            <label>
                                Observación
                                <span class="label-opcional">(opcional)</span>
                            </label>
                            <div class="campo-input">
                                <i class="bi bi-chat-left-text campo-icono"></i>
                                <input type="text" id="efObservacion"
                                    placeholder="Notas adicionales" maxlength="255">
                            </div>
                        </div>

                        <!-- Resumen recálculo -->
                        <div id="efResumen" style="display:none; margin-top:8px;">

                            <div class="ver-saldo">
                                <div class="ver-saldo-fila">
                                    <span>Nuevo total calculado</span>
                                    <strong id="efNuevoTotal"></strong>
                                </div>
                                <div class="ver-saldo-fila">
                                    <span>Ya pagado</span>
                                    <strong id="efMontoPagado" style="color:#198754;"></strong>
                                </div>
                                <div class="ver-saldo-fila ver-saldo-pendiente">
                                    <span id="efSaldoLabel">Saldo pendiente</span>
                                    <strong id="efSaldo"></strong>
                                </div>
                            </div>

                            <!-- Aviso por caso -->
                            <div id="efAvisoCaso" class="aviso-franja" style="display:none; margin-top:12px;"></div>

                            <!-- Pago adicional (solo Caso A) -->
                            <div id="efPagoGrupo" style="display:none; margin-top:16px;">
                                <div class="campo-grupo">
                                    <label>
                                        Monto a pagar ahora
                                        <span id="efPagoMinimoLabel" class="label-opcional"></span>
                                    </label>
                                    <div class="campo-input">
                                        <i class="bi bi-cash campo-icono"></i>
                                        <input type="number" id="efPagoMonto" step="0.01"
                                            placeholder="0.00" oninput="window.efValidarMonto()">
                                    </div>
                                    <div id="efPagoError" style="display:none; color:#dc3545;
                                        font-size:0.78rem; margin-top:4px;"></div>
                                </div>
                                <div class="campo-grupo">
                                    <label>Método de Pago</label>
                                    <div class="campo-select">
                                        <i class="bi bi-wallet2 campo-icono"></i>
                                        <select id="efPagoMetodo">
                                            <option value="">Seleccionar...</option>
                                            @foreach($metodosPago as $metodo)
                                                <option value="{{ $metodo->id }}">
                                                    {{ ucfirst($metodo->nombre) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="efBtnGuardar"
                        style="display:none;" onclick="window.efGuardar()">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL REASIGNAR HABITACIÓN -->
    <div class="modal fade" id="modalReasignar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-arrow-left-right"></i>
                        Reasignar Habitación — Reserva <span id="raReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">

                    <!-- CARGANDO -->
                    <div id="raCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando habitaciones...
                    </div>

                    <!-- CONTENIDO -->
                    <div id="raContenido" style="display:none;">

                        <!-- Info rango -->
                        <div id="raInfoRango" class="aviso-franja franja-normal"
                            style="margin-bottom:16px;"></div>

                        <!-- Habitaciones actuales (selector) -->
                        <div class="piso-label" style="margin-bottom:6px;">
                            Habitaciones de la reserva
                            <span style="opacity:0.6; font-weight:400;">— seleccione cuál reasignar</span>
                        </div>
                        <div class="piso-habitaciones" id="raHabsActuales"></div>

                        <!-- Mapa de alternativas -->
                        <div id="raMapaAlternativas" style="margin-top:20px; display:none;">
                            <div class="piso-label" style="margin-bottom:6px;">
                                Habitaciones disponibles — N° <span id="raNumeroActual"></span>
                            </div>
                            <div id="raMapaContenedor"></div>
                        </div>

                        <!-- Sin alternativas -->
                        <div id="raSinAlternativas" class="paso2-estado" style="display:none; margin-top:20px;">
                            <i class="bi bi-door-closed"></i>
                            No hay habitaciones disponibles del mismo tipo para reasignar.
                        </div>

                        <!-- Aviso sin cambios -->
                        <div id="raAvisoSinCambios" class="aviso-franja franja-horas"
                            style="display:none; margin-top:16px;">
                            <i class="bi bi-info-circle"></i>
                            Seleccione al menos una habitación alternativa para guardar.
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="raBtnGuardar"
                        style="display:none;" onclick="window.raGuardar()">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR HUÉSPEDES -->
    <div class="modal fade" id="modalHuespedes" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-people"></i>
                        Editar Huéspedes — Reserva <span id="huespedReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
    
                <div class="modal-body">
    
                    <!-- CARGANDO -->
                    <div id="huespedCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando huéspedes...
                    </div>
    
                    <!-- CONTENIDO -->
                    <div id="huespedContenido" style="display:none;">
    
                        <!-- Aviso límite -->
                        <div id="huespedAvisoLimite" class="aviso-franja franja-horas"
                            style="display:none; margin-bottom:14px;"></div>
    
                        <!-- BUSCADOR (igual al paso 3) -->
                        <div class="paso3-buscador">
                            <div class="campo-grupo" style="max-width:150px;">
                                <label>Tipo Documento</label>
                                <div class="campo-select">
                                    <i class="bi bi-id-card campo-icono"></i>
                                    <select id="huespedTipoDoc">
                                        <option value="">Todos</option>
                                        @foreach($tiposDocumento as $td)
                                            <option value="{{ $td->id }}">{{ strtoupper($td->nombre) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="campo-grupo" style="max-width:150px;">
                                <label>Nº Documento</label>
                                <div class="campo-input">
                                    <i class="bi bi-123 campo-icono"></i>
                                    <input type="text" id="huespedNumDoc"
                                        placeholder="12345678" maxlength="20">
                                </div>
                            </div>
                            <div class="campo-grupo">
                                <label>Nombre</label>
                                <div class="campo-input">
                                    <i class="bi bi-person campo-icono"></i>
                                    <input type="text" id="huespedNombre"
                                        placeholder="Buscar por nombre..." maxlength="100">
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; padding-bottom:8px;">
                                <button type="button" class="btn-primario"
                                    onclick="window.buscarHuespedModal()">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                                <button type="button" class="btn-secundario"
                                    onclick="window.limpiarBuscadorHuespedModal()">
                                    <i class="bi bi-x-lg"></i> Limpiar
                                </button>
                            </div>
                        </div>
    
                        <!-- RESULTADOS BÚSQUEDA -->
                        <div id="huespedResultados" style="display:none;">
                            <div class="piso-label" style="margin-bottom:6px;">Resultados</div>
                            <div id="huespedListaResultados" class="paso3-lista"></div>
                        </div>
    
                        <!-- SIN RESULTADOS -->
                        <div id="huespedVacio" class="paso2-estado" style="display:none;">
                            <i class="bi bi-person-x"></i>
                            No se encontró ningún huésped con esos datos.
                        </div>
    
                        <!-- HUÉSPEDES EN LA RESERVA -->
                        <div id="huespedSeleccionados" class="paso3-seleccionados"
                            style="display:none; margin-top:16px;">
                            <div class="paso3-seleccionados-titulo">
                                Huéspedes en esta reserva
                                <span id="huespedContadorBadge" class="badge-contador-dorado"></span>
                            </div>
                            <div id="huespedListaSeleccionados"></div>
                        </div>
    
                    </div>
                </div>
    
                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="huespedBtnGuardar"
                        style="display:none;" onclick="window.guardarHuespedes()">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AGREGAR EXTENSIÓN -->
    <div class="modal fade" id="modalExtension" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-clock-history"></i>
                        Agregar Extensión — Reserva <span id="extReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
    
                <div class="modal-body">
    
                    <!-- CARGANDO -->
                    <div id="extCargando" class="paso2-estado" style="display:none;">
                        <i class="bi bi-arrow-repeat"></i>
                        Verificando disponibilidad...
                    </div>
    
                    <!-- FASE A: Ingresar cantidad -->
                    <div id="extFaseA">
    
                        <div id="extTipoLabel" class="piso-label" style="margin-bottom:14px;"></div>
    
                        <div class="campo-grupo">
                            <label id="extCantidadLabel">Cantidad a extender</label>
                            <div class="campo-input">
                                <i class="bi bi-clock campo-icono"></i>
                                <input type="number" id="extCantidad" min="1" step="1"
                                    placeholder="1" value="1"
                                    oninput="window.extLimpiarResultado()">
                            </div>
                        </div>
    
                        <button type="button" class="btn-primario" style="width:100%;"
                            onclick="window.extVerificar()">
                            <i class="bi bi-search"></i> Verificar disponibilidad
                        </button>
    
                    </div>
    
                    <!-- FASE B: Resultado verificación -->
                    <div id="extFaseB" style="display:none; margin-top:20px;">
    
                        <!-- Info salida -->
                        <div id="extInfoSalida" class="aviso-franja franja-normal"
                            style="margin-bottom:14px;"></div>
    
                        <!-- Lista habitaciones -->
                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-door-open"></i> Habitaciones
                            </div>
                            <div id="extHabitaciones"></div>
                        </div>
    
                        <!-- Aviso si hay conflictos -->
                        <div id="extAvisoConflicto" class="aviso-franja franja-horas"
                            style="display:none; margin-top:10px;"></div>
    
                        <!-- FASE C: Pago (solo si hay disponibles) -->
                        <div id="extFaseC" style="display:none; margin-top:16px;">
    
                            <div style="display:flex; justify-content:space-between;
                                align-items:center; border-top:2px solid var(--azul-marino);
                                padding-top:12px; margin-bottom:14px;">
                                <span style="font-size:0.88rem; font-weight:700;
                                    color:var(--azul-marino); text-transform:uppercase;
                                    letter-spacing:0.5px;">Total extensión</span>
                                <strong id="extMontoTotal"
                                    style="font-size:1.1rem; color:var(--azul-marino);"></strong>
                            </div>
    
                            <div class="campo-grupo">
                                <label>Método de Pago</label>
                                <div class="campo-select">
                                    <i class="bi bi-wallet2 campo-icono"></i>
                                    <select id="extMetodoId">
                                        <option value="">Seleccionar...</option>
                                        @foreach($metodosPago as $metodo)
                                            <option value="{{ $metodo->id }}">
                                                {{ ucfirst($metodo->nombre) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="extErrorMetodo" style="display:none; color:#dc3545;
                                    font-size:0.78rem; margin-top:4px;">
                                    Seleccione un método de pago.
                                </div>
                            </div>
    
                        </div>
    
                    </div>
    
                </div>
    
                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="extBtnConfirmar"
                        style="display:none;" onclick="window.extConfirmar()">
                        <i class="bi bi-check-lg"></i> Confirmar Extensión
                    </button>
                </div>
    
            </div>
        </div>
    </div>

    <!-- MODAL FINALIZAR -->
    <div class="modal fade" id="modalFinalizar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-check-circle"></i>
                        Finalizar — Reserva <span id="finReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
    
                <div class="modal-body">
    
                    <!-- CARGANDO -->
                    <div id="finCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando habitaciones...
                    </div>
    
                    <!-- CONTENIDO -->
                    <div id="finContenido" style="display:none;">
    
                        <!-- Toggle masivo -->
                        <div style="display:flex; align-items:center; gap:10px;
                            padding:10px 12px; background:var(--fondo-secundario);
                            border-radius:8px; margin-bottom:14px;">
                            <span style="font-size:0.85rem; font-weight:600;
                                color:var(--gris-texto); flex:1;">
                                Aplicar a todas:
                            </span>
                            <button type="button" class="btn-estado-hab btn-limpieza-masivo"
                                onclick="window.finAplicarTodas('limpieza')">
                                <i class="bi bi-stars"></i> Limpieza
                            </button>
                            <button type="button" class="btn-estado-hab btn-mantenimiento-masivo"
                                onclick="window.finAplicarTodas('mantenimiento')">
                                <i class="bi bi-wrench"></i> Mantenimiento
                            </button>
                        </div>
    
                        <!-- Lista habitaciones -->
                        <div id="finHabitaciones" class="ver-seccion" style="margin-bottom:0;"></div>
    
                        <!-- Aviso falta selección -->
                        <div id="finAvisoIncompleto" class="aviso-franja franja-horas"
                            style="display:none; margin-top:12px;">
                            <i class="bi bi-exclamation-circle"></i>
                            Seleccione el destino de todas las habitaciones.
                        </div>
    
                    </div>
    
                </div>
    
                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="finBtnConfirmar"
                        style="display:none;" onclick="window.finConfirmar()">
                        <i class="bi bi-check-circle"></i> Confirmar Check-out
                    </button>
                </div>
    
            </div>
        </div>
    </div>
    
    <!-- MODAL CANCELAR -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-x-circle"></i>
                        Cancelar Reserva <span id="cancelarReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="aviso-franja franja-horas">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Esta acción no se puede deshacer. Los pagos registrados
                        <strong>no serán devueltos</strong> automáticamente.
                    </div>
                    <p style="margin-top:16px; font-size:0.9rem; color:var(--gris-texto);">
                        ¿Confirma que desea cancelar esta reserva?
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        No, volver
                    </button>
                    <button type="button" class="btn-primario"
                        id="cancelarBtnConfirmar"
                        style="background:var(--bs-danger, #dc3545); border-color:var(--bs-danger, #dc3545);"
                        onclick="window.confirmarCancelar()">
                        <i class="bi bi-x-circle"></i> Sí, cancelar reserva
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.tiposEstadiaData = @json($tiposEstadia->keyBy('id'));
    </script>

    @push('scripts')
        @vite('resources/js/reservas/index.js')
    @endpush

</x-app-layout>
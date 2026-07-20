<x-app-layout>

    <div class="pagina-contenedor">

        {{-- Encabezado: título y acción (nueva reserva) --}}
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Reservas</h1>
            <button class="btn-primario" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="bi bi-plus-lg"></i> Nueva Reserva
            </button>
        </div>

        {{-- Alertas de sesión --}}
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

        {{-- Filtros de búsqueda (AJAX vía buscarReservas) --}}
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
                <button class="btn-primario" onclick="window.buscarReservas(1, false)">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <button class="btn-secundario" onclick="window.limpiarFiltros()">
                    <i class="bi bi-x-lg"></i> Limpiar
                </button>
            </div>

        </div>

        {{-- Tabla de reservas, tbody se llena por JS --}}
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

        {{-- Paginación generada por JS --}}
        <div id="paginacion" class="paginacion-contenedor"></div>

    </div>


    {{-- Modal: nueva reserva — wizard de 4 pasos (Huéspedes → Datos → Habitaciones → Pagos) --}}
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

                        <div class="pasos-indicadores">
                            @foreach(['Huéspedes', 'Datos', 'Habitaciones', 'Pagos'] as $i => $paso)
                                <div class="paso-indicador {{ $i === 0 ? 'paso-activo' : '' }}"
                                    id="indicador{{ $i + 1 }}">
                                    <span class="paso-numero">{{ $i + 1 }}</span>
                                    <span class="paso-label">{{ $paso }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Paso 1: buscador de huéspedes + lista de seleccionados + creación rápida --}}
                        <div id="paso1">

                            <div class="aviso-franja franja-info mb-14">
                                <i class="bi bi-info-circle-fill"></i>
                                Agregue a todos los huéspedes que se hospedarán en esta reserva antes de continuar.
                            </div>

                            <div id="paso3AvisoLimite" class="aviso-franja franja-info mb-14" style="display:none;"></div>

                            <div class="paso3-buscador">
                                <div class="campo-grupo campo-grupo-doc">
                                    <label>Nº Documento</label>
                                    <div class="campo-input">
                                        <i class="bi bi-123 campo-icono"></i>
                                        <input type="text" id="paso3NumDoc" placeholder="12345678" maxlength="20" autocomplete="off">
                                    </div>
                                </div>
                                <div class="campo-grupo">
                                    <label>Nombre</label>
                                    <div class="campo-input">
                                        <i class="bi bi-person campo-icono"></i>
                                        <input type="text" id="paso3Nombre" placeholder="Buscar por nombre..." maxlength="100" autocomplete="off">
                                    </div>
                                </div>
                                <div class="buscador-acciones">
                                    <button type="button" class="btn-primario" id="paso3BtnBuscar" onclick="window.buscarHuesped()">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <button type="button" class="btn-secundario" onclick="window.limpiarBuscadorHuesped()">
                                        <i class="bi bi-x-lg"></i> Limpiar
                                    </button>
                                </div>

                                <small id="paso3ErrorBusqueda" class="campo-error" style="display:none;">
                                    Ingrese número de documento o nombre para buscar.
                                </small>
                            </div>

                            <div id="paso3Resultados" style="display:none;">
                                <div class="piso-label mb-6">Resultados</div>
                                <div id="paso3ListaResultados" class="paso3-lista"></div>
                            </div>

                            <div id="paso3Vacio" class="paso2-estado" style="display:none;">
                                <i class="bi bi-person-x"></i>
                                No se encontró ningún huésped con esos datos.
                                <div class="mt-10">
                                    <button type="button" class="btn-primario" id="paso3BtnCrearNuevo"
                                        style="display:none;" onclick="window.mostrarCrearHuespedInline()">
                                        <i class="bi bi-person-plus"></i> Crear nuevo huésped
                                    </button>
                                </div>
                            </div>

                            {{-- Formulario de creación rápida de huésped, se muestra cuando la búsqueda no arroja resultados --}}
                            <div id="paso3CrearHuesped" class="mt-16" style="display:none;">
                                <div class="piso-label mb-6">
                                    <i class="bi bi-person-plus"></i> Registrar nuevo huésped
                                </div>

                                <div class="campo-grupo">
                                    <label>Nombre completo</label>
                                    <div class="campo-input">
                                        <i class="bi bi-person campo-icono"></i>
                                        <input type="text" id="crearHuespedNombre" placeholder="Ej: Juan Pérez" maxlength="100">
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="campo-grupo">
                                        <label>N° Documento</label>
                                        <div class="campo-input">
                                            <i class="bi bi-hash campo-icono"></i>
                                            <input type="text" id="crearHuespedNumDoc" placeholder="Ej: 12345678" maxlength="20"
                                                oninput="window.verificarDocumentoInline()">
                                        </div>
                                        <small id="errorCrearHuespedDoc" class="campo-error"></small>
                                    </div>

                                    <div class="campo-grupo">
                                        <label>Teléfono <span class="label-opcional">(opcional)</span></label>
                                        <div class="campo-input">
                                            <i class="bi bi-telephone campo-icono"></i>
                                            <input type="text" id="crearHuespedTelefono" placeholder="Ej: 987654321" maxlength="15"
                                                oninput="window.verificarTelefonoInline()">
                                        </div>
                                        <small id="errorCrearHuespedTel" class="campo-error"></small>
                                    </div>
                                </div>

                                <small id="errorCrearHuespedGeneral" class="campo-error" style="display:none;"></small>

                                <div class="filtros-acciones mt-8">
                                    <button type="button" class="btn-primario" id="btnGuardarHuespedInline"
                                        onclick="window.guardarHuespedInline()">
                                        <i class="bi bi-check-lg"></i> Guardar Huésped
                                    </button>
                                    <button type="button" class="btn-secundario" onclick="window.ocultarCrearHuespedInline()">
                                        <i class="bi bi-x-lg"></i> Cancelar
                                    </button>
                                </div>
                            </div>

                            <div id="paso3Seleccionados" class="paso3-seleccionados" style="display:none;">
                                <div class="paso3-seleccionados-titulo">
                                    Huéspedes agregados
                                    <span id="paso3ContadorBadge" class="badge-contador-dorado"></span>
                                </div>
                                <div id="paso3ListaSeleccionados"></div>
                            </div>

                            <small id="paso3ErrorGeneral" class="campo-error" style="display:none;"></small>

                            <div class="mt-16">
                                <button type="button" class="btn-secundario" id="sugerenciaBtnToggle"
                                    onclick="window.toggleSugerencia()" disabled>
                                    <i class="bi bi-chat-square-text"></i> Registrar sugerencia / motivo de no atención
                                </button>

                                <div id="sugerenciaContenedor" class="mt-12" style="display:none;">

                                    <div id="sugerenciaInfo" class="aviso-franja franja-info mb-8" style="display:none;"></div>

                                    <div class="campo-grupo">
                                        <label>Comentario</label>
                                        <div class="campo-input">
                                            <i class="bi bi-chat-left-text campo-icono"></i>
                                            <input type="text" id="sugerenciaComentario" maxlength="255"
                                                placeholder="Ej: Buscaba habitación con vista al mar, no ofrecemos ese tipo."
                                                oninput="window.validarComentarioSugerencia()">
                                        </div>
                                        <small id="sugerenciaErrorComentario" class="campo-error" style="display:none;">
                                            Escriba un comentario antes de guardar.
                                        </small>
                                    </div>

                                    <div class="filtros-acciones mt-8">
                                        <button type="button" class="btn-primario" id="sugerenciaBtnGuardar"
                                            onclick="window.guardarSugerencia()" disabled>
                                            <i class="bi bi-save"></i> Guardar sugerencia
                                        </button>
                                        <button type="button" class="btn-secundario"
                                            onclick="window.toggleSugerencia()">
                                            <i class="bi bi-x-lg"></i> Cerrar
                                        </button>
                                    </div>

                                </div>
                            </div>

                        </div>

                        {{-- Paso 2: tipo de estadía, fechas, aviso de franja horaria --}}
                        <div id="paso2" style="display:none;">

                            <div class="campo-grupo">
                                <label>Tipo de Estadía</label>
                                <div class="campo-select">
                                    <i class="bi bi-clock campo-icono"></i>
                                    <select name="tipo_estadia" id="tipoEstadiaId"
                                        onchange="window.actualizarPaso1()" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="horas">Horas</option>
                                        <option value="noches">Noches</option>
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

                            <div id="avisoFranja" class="aviso-franja" style="display:none;"></div>

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

                                <small id="paso1ErrorGeneral" class="campo-error" style="display:none;">
                                    Complete todos los campos obligatorios.
                                </small>
                            </div>

                        </div>

                        {{-- Paso 3: mapa de habitaciones disponibles, cargado por AJAX --}}
                        <div id="paso3" style="display:none;">

                            <div id="paso2Cargando" class="paso2-estado">
                                <i class="bi bi-arrow-repeat"></i>
                                Buscando habitaciones disponibles...
                            </div>

                            <div id="paso2Mapa" style="display:none;"></div>

                            <div id="paso2Vacio" class="paso2-estado" style="display:none;">
                                <i class="bi bi-door-closed"></i>
                                No hay habitaciones disponibles para el rango seleccionado.
                            </div>

                            <div id="paso2Resumen" class="resumen-seleccion" style="display:none;">
                                <div class="resumen-seleccion-inner">
                                    <span id="paso2ResumenTexto" class="resumen-texto"></span>
                                    <strong id="paso2ResumenTotal" class="resumen-total"></strong>
                                </div>
                            </div>

                            <div id="paso2AvisoCapacidad" class="aviso-franja mt-20" style="display:none;"></div>

                            <small id="paso2ErrorSeleccion" class="campo-error" style="display:none;">
                                Seleccione al menos una habitación.
                            </small>

                        </div>

                        {{-- Paso 4: desglose de costos, monto y método de pago --}}
                        <div id="paso4" style="display:none;">

                            <div class="paso4-desglose">
                                <h6>Desglose de costos</h6>
                                <div id="paso4FilasDesglose"></div>
                                <div class="paso4-fila paso4-fila-total">
                                    <span>Total</span>
                                    <span id="paso4Total">S/ 0.00</span>
                                </div>
                            </div>

                            <div id="paso4AvisoOcupacion" class="aviso-franja" style="display:none;"></div>

                            <div class="form-grid-2">
                                <div class="mb-3">
                                    <label for="paso4MontoPago" class="form-label">
                                        Monto a pagar <span id="paso4MinimoLabel" class="text-muted"></span>
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="paso4MontoPago"
                                        oninput="window.validarMontoPago()">
                                    <div id="paso4ErrorMonto" class="campo-error" style="display:none;"></div>
                                </div>

                                <div class="mb-3">
                                    <label for="paso4MetodoId" class="form-label">Método de pago</label>
                                    <select class="form-select" id="paso4MetodoId" onchange="window.onCambioMetodoPago()">
                                        <option value="">Seleccione...</option>
                                        @foreach($metodosPago as $metodo)
                                            <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                                {{ ucfirst($metodo->nombre) }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <small id="paso4ErrorMetodo" class="campo-error" style="display:none;">
                                        Seleccione un método de pago.
                                    </small>
                                </div>
                            </div>

                            <div class="mb-3" id="paso4GrupoNumeroOperacion" style="display:none;">
                                <label for="paso4NumeroOperacion" class="form-label">Número de operación</label>
                                <input type="text" maxlength="30" class="form-control" id="paso4NumeroOperacion"
                                    placeholder="Ej: 000123456789" oninput="window.validarNumeroOperacion()">
                                <div id="paso4ErrorNumeroOperacion" class="campo-error" style="display:none;">
                                    Ingrese el número de operación.
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
                            <button type="button" class="btn-secundario" onclick="window.confirmarCierreCrear()">
                                Cancelar
                            </button>
                            <button type="button" class="btn-primario" id="btnSiguiente"
                                onclick="window.cambiarPaso(1)">
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </button>
                            <button type="button" class="btn-primario" id="btnConfirmar"
                                style="display:none;" onclick="window.confirmarReserva()">
                                </i> Confirmar Reserva
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


    {{-- Modal: ver detalle (datos cargados por JS: verReserva) --}}
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

                    <div id="verCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información...
                    </div>

                    <div id="verContenido" style="display:none;">

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

                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-door-open"></i> Habitaciones
                            </div>
                            <div id="verHabitaciones"></div>
                        </div>

                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-people"></i> Huéspedes
                            </div>
                            <div id="verHuespedes"></div>
                        </div>

                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-cash-coin"></i> Pagos
                            </div>
                            <div id="verPagos"></div>
                        </div>

                        <div class="ver-seccion" id="verSeccionExtensiones" style="display:none;">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-clock-history"></i> Extensiones
                            </div>
                            <div id="verExtensiones"></div>
                        </div>

                        <div class="ver-seccion" id="verSeccionDevoluciones" style="display:none;">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-arrow-counterclockwise"></i> Devoluciones
                            </div>
                            <div id="verDevoluciones"></div>
                        </div>

                        <div class="ver-seccion" id="verSeccionComprobante" style="display:none;">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-receipt"></i> Comprobante
                            </div>
                            <div id="verComprobante"></div>
                        </div>

                        <div class="ver-saldo">
                            <div class="ver-saldo-fila">
                                <span>Total reserva</span>
                                <strong id="verMontoTotal"></strong>
                            </div>
                            <div class="ver-saldo-fila">
                                <span>Pagado</span>
                                <strong id="verMontoPagado" class="ver-saldo-fila-exito"></strong>
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


    {{-- Modal: registrar pago (datos cargados por JS: pagoReserva) --}}
    <div class="modal fade" id="modalPago" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-angosto">
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

                    <div id="pagoCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información...
                    </div>

                    <div id="pagoContenido" style="display:none;">

                        <div class="ver-saldo mb-20">
                            <div class="ver-saldo-fila">
                                <span>Total reserva</span>
                                <strong id="pagoMontoTotal"></strong>
                            </div>
                            <div class="ver-saldo-fila">
                                <span>Pagado</span>
                                <strong id="pagoMontoPagado" class="ver-saldo-fila-exito"></strong>
                            </div>
                            <div class="ver-saldo-fila ver-saldo-pendiente">
                                <span>Saldo pendiente</span>
                                <strong id="pagoSaldo" class="ver-saldo-fila-peligro"></strong>
                            </div>
                        </div>

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
                            <small id="pagoErrorMonto" class="campo-error" style="display:none;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Método de Pago</label>
                            <div class="campo-select">
                                <i class="bi bi-wallet2 campo-icono"></i>
                                <select id="pagoMetodoId" onchange="window.onCambioMetodoPagoModal()">
                                    <option value="">Seleccionar...</option>
                                    @foreach($metodosPago as $metodo)
                                        <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                            {{ ucfirst($metodo->nombre) }}
                                        </option>
                                    @endforeach
                                </select>

                                <small id="pagoErrorMetodo" class="campo-error" style="display:none;">
                                    Seleccione un método de pago.
                                </small>
                            </div>
                        </div>

                        <div class="campo-grupo" id="pagoGrupoNumeroOperacion" style="display:none;">
                            <label>Número de operación</label>
                            <div class="campo-input">
                                <i class="bi bi-hash campo-icono"></i>
                                <input type="text" id="pagoNumeroOperacion" maxlength="30"
                                    placeholder="Ej: 000123456789" oninput="window.validarNumeroOperacionPago()">
                            </div>
                            <small id="pagoErrorNumeroOperacion" class="campo-error" style="display:none;">
                                Ingrese el número de operación.
                            </small>
                        </div>

                        <div id="pagoAvisoTipo" class="aviso-franja mt-4" style="display:none;"></div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="pagoBtnConfirmar"
                        style="display:none;" onclick="window.confirmarPago()">
                        </i> Confirmar Pago
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- Modal: editar fechas/tipo (datos cargados por JS: editarFechasReserva) --}}
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

                    <div id="efCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información...
                    </div>

                    <div id="efContenido" style="display:none;">

                        <div class="campo-grupo">
                            <label>Tipo de Estadía</label>
                            <div class="campo-select">
                                <i class="bi bi-clock campo-icono"></i>
                                <select id="efTipoEstadiaId" onchange="window.efOnTipoChange()">
                                    <option value="horas">Horas</option>
                                    <option value="noches">Noches</option>
                                </select>
                            </div>
                        </div>

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

                        <div id="efAvisoFranja" class="aviso-franja" style="display:none;"></div>

                        <div id="efAvisoConflicto" class="aviso-franja franja-peligro" style="display:none;"></div>

                        <div class="campo-grupo" id="efObservacionGrupo">
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

                        <small id="efErrorGeneral" class="campo-error" style="display:none;">
                            Complete todos los campos obligatorios.
                        </small>

                        <div id="efResumen" class="mt-8" style="display:none;">

                            <div class="ver-saldo">
                                <div class="ver-saldo-fila">
                                    <span>Nuevo total calculado</span>
                                    <strong id="efNuevoTotal"></strong>
                                </div>
                                <div class="ver-saldo-fila">
                                    <span>Ya pagado</span>
                                    <strong id="efMontoPagado" class="ver-saldo-fila-exito"></strong>
                                </div>
                                <div class="ver-saldo-fila ver-saldo-pendiente">
                                    <span id="efSaldoLabel">Saldo pendiente</span>
                                    <strong id="efSaldo"></strong>
                                </div>
                            </div>

                            <div id="efAvisoCaso" class="aviso-franja mt-12" style="display:none;"></div>

                            {{-- Pago adicional — solo Caso A --}}
                            <div id="efPagoGrupo" class="mt-16" style="display:none;">
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
                                    <small id="efPagoError" class="campo-error" style="display:none;"></small>
                                </div>
                                <div class="campo-grupo">
                                    <label>Método de Pago</label>
                                    <div class="campo-select">
                                        <i class="bi bi-wallet2 campo-icono"></i>
                                        <select id="efPagoMetodo" onchange="window.efOnCambioMetodo()">
                                            <option value="">Seleccionar...</option>
                                            @foreach($metodosPago as $metodo)
                                                <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                                    {{ ucfirst($metodo->nombre) }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <small id="efErrorMetodo" class="campo-error" style="display:none;">
                                            Seleccione un método de pago.
                                        </small>
                                    </div>
                                </div>

                                <div class="campo-grupo" id="efGrupoNumeroOperacion" style="display:none;">
                                    <label>Número de operación</label>
                                    <div class="campo-input">
                                        <i class="bi bi-hash campo-icono"></i>
                                        <input type="text" id="efNumeroOperacion" maxlength="30"
                                            placeholder="Ej: 000123456789" oninput="window.efValidarNumeroOperacion()">
                                    </div>
                                    <small id="efErrorNumeroOperacion" class="campo-error" style="display:none;">
                                        Ingrese el número de operación.
                                    </small>
                                </div>

                            </div>

                            {{-- Crédito a favor — solo Caso C --}}
                            <div id="efCreditoGrupo" class="mt-16" style="display:none;">
                                <div class="campo-grupo">
                                    <label>
                                        Monto a devolver ahora
                                        <span id="efCreditoMaximoLabel" class="label-opcional"></span>
                                    </label>
                                    <div class="campo-input">
                                        <i class="bi bi-cash campo-icono"></i>
                                        <input type="number" id="efCreditoMontoDevuelto" step="0.01"
                                            placeholder="0.00" oninput="window.efValidarCredito()">
                                    </div>
                                    <small id="efCreditoError" class="campo-error" style="display:none;"></small>
                                </div>

                                <div id="efCreditoRetenidoInfo" class="aviso-franja franja-info mt-4"></div>

                                <div class="campo-grupo mt-12" id="efCreditoMetodoGrupo" style="display:none;">
                                    <label>Método de Devolución</label>
                                    <div class="campo-select">
                                        <i class="bi bi-wallet2 campo-icono"></i>
                                        <select id="efCreditoMetodo" onchange="window.efOnCambioMetodoCredito()">
                                            <option value="">Seleccionar...</option>
                                            @foreach($metodosPago as $metodo)
                                                <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                                    {{ ucfirst($metodo->nombre) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <small id="efCreditoErrorMetodo" class="campo-error" style="display:none;">
                                        Seleccione un método para la devolución.
                                    </small>
                                </div>

                                <div class="campo-grupo mt-12" id="efCreditoGrupoNumeroOperacion" style="display:none;">
                                    <label>Número de operación</label>
                                    <div class="campo-input">
                                        <i class="bi bi-hash campo-icono"></i>
                                        <input type="text" id="efCreditoNumeroOperacion" maxlength="30"
                                            placeholder="Ej: 000123456789" oninput="window.efValidarNumeroOperacionCredito()">
                                    </div>
                                    <small id="efCreditoErrorNumeroOperacion" class="campo-error" style="display:none;">
                                        Ingrese el número de operación.
                                    </small>
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
                        </i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Modal: reasignar habitaciones (datos cargados por JS: reasignarReserva) --}}
    <div class="modal fade" id="modalReasignar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo">
                        <i class="bi bi-arrow-left-right"></i>
                        Reasignar Habitaciones — Reserva <span id="raReservaId"></span>
                    </h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">

                    <div id="raCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando habitaciones...
                    </div>

                    <div id="raContenido" style="display:none;">

                        <div class="piso-label mb-6">
                            Habitaciones de la reserva
                            <span style="opacity:0.6; font-weight:400;">— seleccione cuál reasignar</span>
                        </div>
                        <div class="piso-habitaciones" id="raHabsActuales"></div>

                        <div id="raMapaAlternativas" class="mt-20" style="display:none;">
                            <div class="piso-label mb-6">
                                Habitaciones disponibles
                            </div>
                            <div id="raMapaContenedor"></div>
                        </div>

                        <div id="raSinAlternativas" class="paso2-estado mt-20" style="display:none;">
                            <i class="bi bi-door-closed"></i>
                            No hay habitaciones disponibles del mismo tipo para reasignar.
                        </div>

                        <div id="raAvisoSinCambios" class="aviso-franja franja-advertencia mt-16"
                            style="display:none;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
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
                        </i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Modal: editar huéspedes (datos cargados por JS: huespedesReserva) --}}
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

                    <div id="huespedCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando huéspedes...
                    </div>

                    <div id="huespedContenido" style="display:none;">

                        <div id="huespedAvisoLimite" class="aviso-franja franja-info mb-14" style="display:none;"></div>

                        <div class="paso3-buscador">
                            <div class="campo-grupo campo-grupo-doc">
                                <label>Nº Documento</label>
                                <div class="campo-input">
                                    <i class="bi bi-123 campo-icono"></i>
                                    <input type="text" id="huespedNumDoc" placeholder="12345678" maxlength="20" autocomplete="off">
                                </div>
                            </div>
                            <div class="campo-grupo">
                                <label>Nombre</label>
                                <div class="campo-input">
                                    <i class="bi bi-person campo-icono"></i>
                                    <input type="text" id="huespedNombre" placeholder="Buscar por nombre..." maxlength="100" autocomplete="off">
                                </div>
                            </div>
                            <div class="buscador-acciones">
                                <button type="button" class="btn-primario" id="huespedBtnBuscar"
                                    onclick="window.buscarHuespedModal()">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                                <button type="button" class="btn-secundario"
                                    onclick="window.limpiarBuscadorHuespedModal()">
                                    <i class="bi bi-x-lg"></i> Limpiar
                                </button>
                            </div>

                            <small id="huespedErrorBusqueda" class="campo-error" style="display:none;">
                                Ingrese número de documento o nombre para buscar.
                            </small>
                        </div>

                        <div id="huespedResultados" style="display:none;">
                            <div class="piso-label mb-6">Resultados</div>
                            <div id="huespedListaResultados" class="paso3-lista"></div>
                        </div>

                        <div id="huespedVacio" class="paso2-estado" style="display:none;">
                            <i class="bi bi-person-x"></i>
                            No se encontró ningún huésped con esos datos.
                        </div>

                        <div id="huespedSeleccionados" class="paso3-seleccionados mt-16" style="display:none;">
                            <div class="paso3-seleccionados-titulo">
                                Huéspedes en esta reserva
                                <span id="huespedContadorBadge" class="badge-contador-dorado"></span>
                            </div>
                            <div id="huespedListaSeleccionados"></div>
                        </div>

                        <small id="huespedErrorGeneral" class="campo-error" style="display:none;"></small>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="huespedBtnGuardar"
                        style="display:none;" onclick="window.guardarHuespedes()">
                        </i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Modal: check-in (datos cargados por JS: checkinReserva) --}}
    <div class="modal fade" id="modalCheckin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-angosto">
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

                        <div id="checkinRecargo" class="aviso-franja franja-info mt-12" style="display:none;"></div>

                        <div id="checkinMetodoGrupo" class="campo-grupo mt-16" style="display:none;">
                            <label>Método de Pago del Recargo</label>
                            <div class="campo-select">
                                <i class="bi bi-wallet2 campo-icono"></i>
                                <select id="checkinMetodoId" onchange="window.onCambioMetodoCheckin()">
                                    <option value="">Seleccionar...</option>
                                    @foreach($metodosPago as $metodo)
                                        <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                            {{ ucfirst($metodo->nombre) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <small id="checkinErrorMetodo" class="campo-error" style="display:none;">
                                Seleccione un método de pago para el recargo.
                            </small>
                        </div>

                        <div class="campo-grupo mt-12" id="checkinGrupoNumeroOperacion" style="display:none;">
                            <label>Número de operación</label>
                            <div class="campo-input">
                                <i class="bi bi-hash campo-icono"></i>
                                <input type="text" id="checkinNumeroOperacion" maxlength="30"
                                    placeholder="Ej: 000123456789" oninput="window.validarNumeroOperacionCheckin()">
                            </div>
                            <small id="checkinErrorNumeroOperacion" class="campo-error" style="display:none;">
                                Ingrese el número de operación.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="checkinBtnConfirmar"
                        style="display:none;" onclick="window.confirmarCheckin()">
                        </i> Confirmar Check-in
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Modal: agregar extensión (datos cargados por JS: extensionReserva) --}}
    <div class="modal fade" id="modalExtension" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-medio">
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

                    <div id="extCargando" class="paso2-estado" style="display:none;">
                        <i class="bi bi-arrow-repeat"></i>
                        Verificando disponibilidad...
                    </div>

                    {{-- Fase A: ingresar cantidad --}}
                    <div id="extFaseA">

                        <div id="extTipoLabel" class="piso-label mb-14"></div>

                        <div class="campo-grupo">
                            <label id="extCantidadLabel">Cantidad a extender</label>
                            <div class="campo-input">
                                <i class="bi bi-clock campo-icono"></i>
                                <input type="number" id="extCantidad" min="1" step="1"
                                    placeholder="1" value="1"
                                    oninput="window.extLimpiarResultado()">
                            </div>

                            <small id="extErrorCantidad" class="campo-error" style="display:none;">
                                La cantidad mínima es 1.
                            </small>
                        </div>

                        <button type="button" class="btn-primario" style="width:100%;"
                            onclick="window.extVerificar()">
                            <i class="bi bi-search"></i> Verificar disponibilidad
                        </button>

                    </div>

                    {{-- Fase B: resultado de la verificación --}}
                    <div id="extFaseB" class="mt-20" style="display:none;">

                        <div id="extInfoSalida" class="aviso-franja franja-info mb-14"></div>

                        <div id="extAccionesMasivas" class="ext-acciones-masivas">
                            <button type="button" class="btn-secundario ext-btn-masivo"
                                onclick="window.extSeleccionarTodas(true)">Marcar todas</button>
                            <button type="button" class="btn-secundario ext-btn-masivo"
                                onclick="window.extSeleccionarTodas(false)">Ninguna</button>
                        </div>

                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-door-open"></i> Habitaciones
                            </div>
                            <div id="extHabitaciones"></div>
                        </div>

                        <div id="extAvisoSinSeleccion" class="aviso-franja franja-advertencia mt-10" style="display:none;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Seleccione al menos una habitación para continuar.
                        </div>

                        <div id="extAvisoConflicto" class="aviso-franja franja-info mt-10" style="display:none;"></div>

                        {{-- Fase C: pago — solo si hay habitaciones disponibles --}}
                        <div id="extFaseC" class="mt-16" style="display:none;">

                            <div class="paso4-fila ext-total-fila">
                                <span class="paso4-fila-label ext-total-label">Total extensión</span>
                                <strong id="extMontoTotal" class="ext-total-monto"></strong>
                            </div>

                            <div class="campo-grupo">
                                <label>Método de Pago</label>
                                <div class="campo-select">
                                    <i class="bi bi-wallet2 campo-icono"></i>
                                    <select id="extMetodoId" onchange="window.onCambioMetodoExtension()">
                                        <option value="">Seleccionar...</option>
                                        @foreach($metodosPago as $metodo)
                                            <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                                {{ ucfirst($metodo->nombre) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <small id="extErrorMetodo" class="campo-error" style="display:none;">
                                    Seleccione un método de pago.
                                </small>
                            </div>

                            <div class="campo-grupo" id="extGrupoNumeroOperacion" style="display:none;">
                                <label>Número de operación</label>
                                <div class="campo-input">
                                    <i class="bi bi-hash campo-icono"></i>
                                    <input type="text" id="extNumeroOperacion" maxlength="30"
                                        placeholder="Ej: 000123456789" oninput="window.validarNumeroOperacionExtension()">
                                </div>
                                <small id="extErrorNumeroOperacion" class="campo-error" style="display:none;">
                                    Ingrese el número de operación.
                                </small>
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
                        </i> Confirmar Extensión
                    </button>
                </div>

            </div>
        </div>
    </div>


    {{-- Modal: finalizar / check-out (datos cargados por JS: finalizarReserva) --}}
    <div class="modal fade" id="modalFinalizar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-angosto">
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

                    <div id="finCargando" class="paso2-estado">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando habitaciones...
                    </div>

                    <div id="finContenido" style="display:none;">

                        <div class="filtros-acciones fin-toggle-masivo">
                            <span class="paso4-fila-label fin-toggle-masivo-label">
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

                        <div id="finHabitaciones" class="ver-seccion mb-16"></div>

                        <div id="finAvisoIncompleto" class="aviso-franja franja-advertencia mb-12"
                            style="display:none;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Seleccione el destino de todas las habitaciones.
                        </div>

                        {{-- Comprobante: boleta o factura --}}
                        <div class="ver-seccion">
                            <div class="ver-seccion-titulo">
                                <i class="bi bi-receipt"></i> Comprobante
                            </div>

                            <div class="fin-comprobante-opciones">
                                <button type="button" class="btn-estado-hab fin-comprobante-btn" id="finBtnBoleta"
                                    onclick="window.finSeleccionarComprobante('boleta')">
                                    <i class="bi bi-receipt"></i> Boleta
                                </button>
                                <button type="button" class="btn-estado-hab fin-comprobante-btn" id="finBtnFactura"
                                    onclick="window.finSeleccionarComprobante('factura')">
                                    <i class="bi bi-file-earmark-text"></i> Factura
                                </button>
                            </div>

                            <div id="finInfoBoleta" class="aviso-franja franja-info" style="display:none;"></div>

                            <div id="finGrupoFactura" style="display:none;">
                                <div class="campo-grupo">
                                    <label>RUC</label>
                                    <div class="campo-input">
                                        <i class="bi bi-building campo-icono"></i>
                                        <input type="text" id="finRuc" maxlength="11" inputmode="numeric"
                                            placeholder="20123456789" oninput="window.finValidarRuc()">
                                    </div>
                                    <small id="finErrorRuc" class="campo-error" style="display:none;">
                                        Ingrese un RUC válido (11 dígitos).
                                    </small>
                                </div>
                                <div class="campo-grupo">
                                    <label>Razón Social</label>
                                    <div class="campo-input">
                                        <i class="bi bi-signpost campo-icono"></i>
                                        <input type="text" id="finRazonSocial" maxlength="150"
                                            placeholder="Nombre de la empresa" oninput="window.finValidarRazonSocial()">
                                    </div>
                                    <small id="finErrorRazonSocial" class="campo-error" style="display:none;">
                                        Ingrese la razón social.
                                    </small>
                                </div>
                            </div>

                            <small id="finErrorComprobante" class="campo-error" style="display:none;">
                                Seleccione el tipo de comprobante.
                            </small>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-primario" id="finBtnConfirmar"
                        style="display:none;" onclick="window.finConfirmar()">
                        </i> Confirmar Check-out
                    </button>
                </div>

            </div>
        </div>
    </div>


    {{-- Modal: cancelar (datos cargados por JS: cancelarReserva) --}}
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-angosto">
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

                    <div class="aviso-franja franja-peligro">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        Esta acción no se puede deshacer. Las habitaciones quedarán disponibles
                        y la reserva pasará a estado <strong>cancelada</strong>.
                    </div>

                    <div id="cancelarCargando" class="paso2-estado mt-16">
                        <i class="bi bi-arrow-repeat"></i>
                        Cargando información de pagos...
                    </div>

                    <div id="cancelarContenido" class="mt-16" style="display:none;">

                        <div class="ver-saldo mb-20">
                            <div class="ver-saldo-fila">
                                <span>Total reserva</span>
                                <strong id="cancelarMontoTotal"></strong>
                            </div>
                            <div class="ver-saldo-fila">
                                <span>Pagado</span>
                                <strong id="cancelarMontoPagado" class="ver-saldo-fila-exito"></strong>
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>
                                Monto a devolver
                                <span id="cancelarMaximoLabel" class="label-opcional"></span>
                            </label>
                            <div class="campo-input">
                                <i class="bi bi-cash campo-icono"></i>
                                <input type="number" id="cancelarMontoDevuelto" step="0.01" min="0"
                                    placeholder="0.00" oninput="window.cancelValidarMonto()">
                            </div>
                            <small id="cancelarErrorMonto" class="campo-error" style="display:none;"></small>
                        </div>

                        <div id="cancelarRetenidoInfo" class="aviso-franja franja-info mt-4"></div>

                        <div class="campo-grupo mt-16" id="cancelarMetodoGrupo" style="display:none;">
                            <label>Método de Devolución</label>
                            <div class="campo-select">
                                <i class="bi bi-wallet2 campo-icono"></i>
                                <select id="cancelarMetodoId" onchange="window.onCambioMetodoCancelar()">
                                    <option value="">Seleccionar...</option>
                                    @foreach($metodosPago as $metodo)
                                        <option value="{{ $metodo->id }}" data-nombre="{{ $metodo->nombre }}">
                                            {{ ucfirst($metodo->nombre) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <small id="cancelarErrorMetodo" class="campo-error" style="display:none;">
                                Seleccione un método de devolución.
                            </small>
                        </div>

                        <div class="campo-grupo mt-12" id="cancelarGrupoNumeroOperacion" style="display:none;">
                            <label>Número de operación</label>
                            <div class="campo-input">
                                <i class="bi bi-hash campo-icono"></i>
                                <input type="text" id="cancelarNumeroOperacion" maxlength="30"
                                    placeholder="Ej: 000123456789" oninput="window.validarNumeroOperacionCancelar()">
                            </div>
                            <small id="cancelarErrorNumeroOperacion" class="campo-error" style="display:none;">
                                Ingrese el número de operación.
                            </small>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">
                        No, volver
                    </button>
                    <button type="button" class="btn-peligro" id="cancelarBtnConfirmar"
                        style="display:none;" onclick="window.confirmarCancelar()">
                        </i> Sí, cancelar reserva
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/reservas/index.js')
    @endpush

</x-app-layout>
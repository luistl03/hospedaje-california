<x-app-layout>

    <div class="pagina-contenedor">

        <!-- ENCABEZADO -->
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Habitaciones</h1>
            <div style="display:flex; gap:10px;">
                <button class="btn-secundario" data-bs-toggle="modal" data-bs-target="#modalTipos">
                    <i class="bi bi-tags"></i> Gestionar Tipos
                </button>
                <button class="btn-primario" data-bs-toggle="modal" data-bs-target="#modalCrear">
                    <i class="bi bi-plus-lg"></i> Nueva Habitación
                </button>
            </div>
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

            <div class="filtro-grupo filtro-grupo-sm">
                <label>N° Hab.</label>
                <div class="campo-input">
                    <i class="bi bi-hash campo-icono"></i>
                    <input type="number" id="filtroNumero" min="1" placeholder="101">
                </div>
            </div>

            <div class="filtro-grupo filtro-grupo-sm">
                <label>Piso</label>
                <div class="campo-input">
                    <i class="bi bi-layers campo-icono"></i>
                    <input type="number" id="filtroPiso" min="1" placeholder="1">
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Tipo</label>
                <div class="campo-select">
                    <i class="bi bi-tag campo-icono"></i>
                    <select id="filtroTipo">
                        <option value="">Todos</option>
                        @foreach($tipos as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Estado</label>
                <div class="campo-select">
                    <i class="bi bi-circle campo-icono"></i>
                    <select id="filtroEstado">
                        <option value="">Todos</option>
                        @foreach($estados as $estado)
                            <option value="{{ $estado->id }}">{{ ucfirst($estado->nombre) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Activo</label>
                <div class="campo-select">
                    <i class="bi bi-toggle-on campo-icono"></i>
                    <select id="filtroActivo">
                        <option value="">Todos</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="filtros-acciones">
                <button class="btn-primario" onclick="window.buscarHabitaciones()">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <button class="btn-secundario" onclick="window.limpiarFiltros()">
                    <i class="bi bi-x-lg"></i> Limpiar
                </button>
            </div>

        </div>

        <!-- TABLA HABITACIONES -->
        <div class="tabla-contenedor">
            <table class="tabla" id="tablaHabitaciones">
                <thead>
                    <tr>
                        <th>N° Hab.</th>
                        <th>Tipo</th>
                        <th>Precio Hora</th>
                        <th>Precio Noche</th>
                        <th>Estado</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div id="paginacion" style="display:none; justify-content:center; margin-top:20px; gap:4px; flex-wrap:wrap;"></div>
    </div>

    <!-- MODAL GESTIONAR TIPOS -->
    <div class="modal fade" id="modalTipos" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-tags"></i> Tipos de Habitación</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">

                    @if(session('error'))
                        <div class="login-error mb-3">
                            <i class="bi bi-exclamation-circle"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Tabla de tipos -->
                    <div class="tabla-contenedor mb-4">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Precio Hora</th>
                                    <th>Precio Noche</th>
                                    <th>Máx. Huésp.</th>
                                    <th>Descripción</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($todosLosTipos as $tipo)
                                <tr>
                                    <td><strong>{{ $tipo->nombre }}</strong></td>
                                    <td>S/ {{ number_format($tipo->precio_hora, 2) }}</td>
                                    <td>S/ {{ number_format($tipo->precio_noche, 2) }}</td>
                                    <td>{{ $tipo->max_huespedes }}</td>
                                    <td>{{ $tipo->descripcion ?? '—' }}</td>
                                    <td>
                                        <span class="badge-estado {{ $tipo->activo ? 'badge-activo' : 'badge-inactivo' }}">
                                            {{ $tipo->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="acciones">
                                        <button class="btn-accion btn-editar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarTipo"
                                            data-id="{{ $tipo->id }}"
                                            data-nombre="{{ $tipo->nombre }}"
                                            data-precio-hora="{{ $tipo->precio_hora }}"
                                            data-precio-noche="{{ $tipo->precio_noche }}"
                                            data-max-huespedes="{{ $tipo->max_huespedes }}"
                                            data-descripcion="{{ $tipo->descripcion }}"
                                            data-activo="{{ (int) $tipo->activo }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn-accion btn-eliminar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEliminarTipo"
                                            data-id="{{ $tipo->id }}"
                                            data-nombre="{{ $tipo->nombre }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Formulario crear tipo -->
                    <div style="border-top: 2px solid var(--gris-borde); padding-top: 20px;">
                        <h6 style="color: var(--azul-marino); font-weight: 700; margin-bottom: 16px;">
                            <i class="bi bi-plus-circle"></i> Nuevo Tipo
                        </h6>
                        <form method="POST" action="{{ route('tipos.store') }}" id="formCrearTipo"
                            onsubmit="return validarFormularioTipo('errorNombreTipoCrear')">
                            @csrf
                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">

                                <div class="campo-grupo">
                                    <label>Nombre</label>
                                    <div class="campo-input">
                                        <i class="bi bi-tag campo-icono"></i>
                                        <input type="text" name="nombre" id="crearTipoNombre"
                                            placeholder="Ej: Simple"
                                            oninput="verificarNombreTipo(this, 'errorNombreTipoCrear')"
                                            required>
                                    </div>
                                    <small id="errorNombreTipoCrear" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                                </div>

                                <div class="campo-grupo">
                                    <label>Precio por Hora</label>
                                    <div class="campo-input">
                                        <i class="bi bi-clock campo-icono"></i>
                                        <input type="number" name="precio_hora" placeholder="0.00" min="0" step="0.01" required>
                                    </div>
                                </div>

                                <div class="campo-grupo">
                                    <label>Precio por Noche</label>
                                    <div class="campo-input">
                                        <i class="bi bi-moon campo-icono"></i>
                                        <input type="number" name="precio_noche" placeholder="0.00" min="0" step="0.01" required>
                                    </div>
                                </div>

                            </div>
                            
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">

                                <div class="campo-grupo">
                                    <label>Máx. Huéspedes</label>
                                    <div class="campo-input">
                                        <i class="bi bi-people campo-icono"></i>
                                        <input type="text" inputmode="numeric" name="max_huespedes"
                                            id="crearMaxHuespedes"
                                            placeholder="Ej: 2" value="1"
                                            oninput="formatearEnteroPositivo(this)">
                                    </div>
                                </div>    
                            
                                <div class="campo-grupo">
                                    <label>Descripción <span style="color: var(--gris-texto); font-size:0.75rem;">(opcional)</span></label>
                                    <div class="campo-input">
                                        <i class="bi bi-chat-left-text campo-icono"></i>
                                        <input type="text" name="descripcion" placeholder="Descripción del tipo">
                                    </div>
                                </div>
                                
                            </div>

                            <div style="text-align:right;">
                                <button type="submit" class="btn-primario">
                                    <i class="bi bi-plus-lg"></i> Agregar Tipo
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR TIPO -->
    <div class="modal fade" id="modalEditarTipo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-pencil-square"></i> Editar Tipo</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="" id="formEditarTipo"
                    onsubmit="return validarFormularioTipo('errorNombreTipoEditar')">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">

                            <div class="campo-grupo" style="grid-column: 1 / -1;">
                                <label>Nombre</label>
                                <div class="campo-input">
                                    <i class="bi bi-tag campo-icono"></i>
                                    <input type="text" name="nombre" id="editTipoNombre"
                                        placeholder="Ej: Simple"
                                        oninput="verificarNombreTipo(this, 'errorNombreTipoEditar', document.getElementById('editTipoId').value)"
                                        required>
                                </div>
                                <small id="errorNombreTipoEditar" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                                <input type="hidden" id="editTipoId" value="">
                            </div>

                            <div class="campo-grupo">
                                <label>Precio por Hora</label>
                                <div class="campo-input">
                                    <i class="bi bi-clock campo-icono"></i>
                                    <input type="number" name="precio_hora" id="editTipoPrecioHora" placeholder="0.00" min="0" step="0.01" required>
                                </div>
                            </div>

                            <div class="campo-grupo">
                                <label>Precio por Noche</label>
                                <div class="campo-input">
                                    <i class="bi bi-moon campo-icono"></i>
                                    <input type="number" name="precio_noche" id="editTipoPrecioNoche" placeholder="0.00" min="0" step="0.01" required>
                                </div>
                            </div>

                        </div>
                
                        <div class="campo-grupo">
                            <label>Máx. Huéspedes</label>
                            <div class="campo-input">
                                <i class="bi bi-people campo-icono"></i>
                                <input type="text" inputmode="numeric" name="max_huespedes"
                                    id="editMaxHuespedes"
                                    placeholder="Ej: 2"
                                    oninput="formatearEnteroPositivo(this)">
                            </div>
                        </div>                        

                        <div class="campo-grupo">
                            <label>Descripción <span style="color: var(--gris-texto); font-size:0.75rem;">(opcional)</span></label>
                            <div class="campo-input">
                                <i class="bi bi-chat-left-text campo-icono"></i>
                                <input type="text" name="descripcion" id="editTipoDescripcion" placeholder="Descripción del tipo">
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>Estado</label>
                            <div class="campo-select">
                                <i class="bi bi-toggle-on campo-icono"></i>
                                <select name="activo" id="editTipoActivo" required>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primario">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ELIMINAR TIPO -->
    <div class="modal fade" id="modalEliminarTipo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-exclamation-triangle"></i> Eliminar Tipo</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p style="color: var(--gris-texto); font-size: 0.9rem;">
                        ¿Estás seguro que deseas eliminar el tipo
                        <strong id="eliminarTipoNombre" style="color: var(--azul-marino);"></strong>?
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="formEliminarTipo">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-peligro">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CREAR HABITACIÓN -->
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-door-open"></i> Nueva Habitación</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('habitaciones.store') }}" id="formCrear"
                    onsubmit="return validarFormularioHabitacion('errorNumeroCrear')">
                    @csrf
                    <div class="modal-body">

                        <div class="campo-grupo">
                            <label>N° Habitación</label>
                            <div class="campo-input">
                                <i class="bi bi-hash campo-icono"></i>
                                <input type="number" name="numero" id="crearNumero"
                                    placeholder="Ej: 101"
                                    oninput="verificarNumero(this, 'errorNumeroCrear')"
                                    min="1" required>
                            </div>
                            <small id="errorNumeroCrear" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Tipo de Habitación</label>
                            <div class="campo-select">
                                <i class="bi bi-tag campo-icono"></i>
                                <select name="tipo_id" required>
                                    <option value="">Seleccionar tipo...</option>
                                    @foreach($tipos as $tipo)
                                        <option value="{{ $tipo->id }}">
                                            {{ $tipo->nombre }} — S/ {{ number_format($tipo->precio_hora, 2) }} hora / S/ {{ number_format($tipo->precio_noche, 2) }} noche
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primario">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR HABITACIÓN -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-pencil-square"></i> Editar Habitación</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="" id="formEditar"
                    onsubmit="return validarFormularioHabitacion('errorNumeroEditar')">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <input type="hidden" id="editNumeroOriginal" value="">
                        <input type="hidden" id="editPaginaActual" name="pagina_actual" value="1">

                        <div class="campo-grupo">
                            <label>N° Habitación</label>
                            <div class="campo-input">
                                <i class="bi bi-hash campo-icono"></i>
                                <input type="number" name="numero" id="editNumero"
                                    oninput="verificarNumero(this, 'errorNumeroEditar', document.getElementById('editNumeroOriginal').value)"
                                    min="1" required>
                            </div>
                            <small id="errorNumeroEditar" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Tipo de Habitación</label>
                            <div class="campo-select">
                                <i class="bi bi-tag campo-icono"></i>
                                <select name="tipo_id" id="editTipo" required>
                                    @foreach($tipos as $tipo)
                                        <option value="{{ $tipo->id }}">
                                            {{ $tipo->nombre }} — S/ {{ number_format($tipo->precio_hora, 2) }} hora / S/ {{ number_format($tipo->precio_noche, 2) }} noche
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>Estado</label>
                            <div class="campo-select">
                                <i class="bi bi-circle campo-icono"></i>
                                <select name="estado_id" id="editEstado" required>
                                    @foreach($estados as $estado)
                                        <option value="{{ $estado->id }}">{{ ucfirst($estado->nombre) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>Activo</label>
                            <div class="campo-select">
                                <i class="bi bi-toggle-on campo-icono"></i>
                                <select name="activo" id="editActivo" required>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primario">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ELIMINAR HABITACIÓN -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-exclamation-triangle"></i> Eliminar Habitación</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p style="color: var(--gris-texto); font-size: 0.9rem;">
                        ¿Estás seguro que deseas eliminar la habitación
                        <strong id="eliminarNumero" style="color: var(--azul-marino);"></strong>?
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-secundario" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="formEliminar">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-peligro">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.paginaRetorno = {{ session('pagina_retorno', 1) }};
    </script>

    @push('scripts')
        @vite('resources/js/habitaciones/index.js')
    @endpush

</x-app-layout>
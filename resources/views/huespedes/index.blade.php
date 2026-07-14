<x-app-layout>

    <div class="pagina-contenedor">

        {{-- Encabezado: título y acciones (nuevo huesped) --}}
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Huéspedes</h1>
            <button class="btn-primario" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="bi bi-plus-lg"></i> Nuevo Huésped
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

        {{-- Filtros de búsqueda (AJAX vía buscarHuespedes) --}}
        <div class="filtros-barra">

            <div class="filtro-grupo">
                <label>Nombre</label>
                <div class="campo-input">
                    <i class="bi bi-person campo-icono"></i>
                    <input type="text" id="filtroNombre" placeholder="Ej: Juan">
                </div>
            </div>

            <div class="filtro-grupo">
                <label>N° Documento</label>
                <div class="campo-input">
                    <i class="bi bi-hash campo-icono"></i>
                    <input type="text" id="filtroNumDoc" placeholder="Ej: 12345678">
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Teléfono</label>
                <div class="campo-input">
                    <i class="bi bi-telephone campo-icono"></i>
                    <input type="text" id="filtroTelefono" placeholder="Ej: 987654321">
                </div>
            </div>

            <div class="filtro-grupo filtro-grupo-sm">
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
                <button class="btn-primario" onclick="window.buscarHuespedes()">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <button class="btn-secundario" onclick="window.limpiarFiltros()">
                    <i class="bi bi-x-lg"></i> Limpiar
                </button>
            </div>

        </div>

        {{-- Tabla de huespedes, tbody se llena por JS --}}
        <div class="tabla-contenedor">
            <table class="tabla" id="tablaHuespedes">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>N° Documento</th>
                        <th>Teléfono</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>

        {{-- Paginación generada por JS --}}
        <div id="paginacion" class="paginacion-contenedor"></div>

    </div>


    {{-- Modal: nuevo huesped --}}
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-person-plus"></i> Nuevo Huésped</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('huespedes.store') }}" id="formCrear"
                    onsubmit="return window.validarFormulario('errorDocCrear', 'errorTelCrear')">
                    @csrf
                    <div class="modal-body">

                        <div class="campo-grupo">
                            <label>Nombre completo</label>
                            <div class="campo-input">
                                <i class="bi bi-person campo-icono"></i>
                                <input type="text" name="nombre" placeholder="Ej: Juan Pérez" maxlength="100" required>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="campo-grupo">
                                <label>N° Documento</label>
                                <div class="campo-input">
                                    <i class="bi bi-hash campo-icono"></i>
                                    <input type="text" name="num_doc" id="crearNumDoc"
                                        placeholder="Ej: 12345678"
                                        oninput="window.verificarDocumento('crearNumDoc', 'errorDocCrear')"
                                        maxlength="20" required>
                                </div>
                                <small id="errorDocCrear" class="campo-error"></small>
                            </div>

                            <div class="campo-grupo">
                                <label>Teléfono <span class="label-opcional">(opcional)</span></label>
                                <div class="campo-input">
                                    <i class="bi bi-telephone campo-icono"></i>
                                    <input type="text" name="telefono" id="crearTelefono"
                                        placeholder="Ej: 987654321"
                                        oninput="window.verificarTelefono('crearTelefono', 'errorTelCrear')"
                                        maxlength="15">
                                </div>
                                <small id="errorTelCrear" class="campo-error"></small>
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


    {{-- Modal: editar habitación (datos cargados por JS, aviso de historial cuando aplica) --}}
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-pencil-square"></i> Editar Huésped</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="" id="formEditar"
                    onsubmit="return window.validarFormulario('errorDocEditar', 'errorTelEditar')">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">

                        {{-- Guarda el num_doc ORIGINAL (antes de editar) para excluirlo en validaciones AJAX --}}
                        <input type="hidden" id="editNumDocOriginal" value="">
                        <input type="hidden" id="editPaginaActual" name="pagina_actual" value="1">

                        <div class="campo-grupo">
                            <label>Nombre completo</label>
                            <div class="campo-input">
                                <i class="bi bi-person campo-icono"></i>
                                <input type="text" name="nombre" id="editNombre" maxlength="100" required>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="campo-grupo">
                                <label>N° Documento</label>
                                <div class="campo-input">
                                    <i class="bi bi-hash campo-icono"></i>
                                    <input type="text" name="num_doc" id="editNumDoc"
                                        oninput="window.verificarDocumento('editNumDoc', 'errorDocEditar', document.getElementById('editNumDocOriginal').value)"
                                        maxlength="20" required>
                                </div>
                                <small id="errorDocEditar" class="campo-error"></small>
                            </div>

                            <div class="campo-grupo">
                                <label>Teléfono <span class="label-opcional">(opcional)</span></label>
                                <div class="campo-input">
                                    <i class="bi bi-telephone campo-icono"></i>
                                    <input type="text" name="telefono" id="editTelefono"
                                        oninput="window.verificarTelefono('editTelefono', 'errorTelEditar', document.getElementById('editNumDocOriginal').value)"
                                        maxlength="15">
                                </div>
                                <small id="errorTelEditar" class="campo-error"></small>
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

    
    {{-- Modal: editar habitación (datos cargados por JS, aviso de historial cuando aplica) --}}
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-exclamation-triangle"></i> Eliminar Huésped</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p class="modal-eliminar-texto">
                        ¿Estás seguro que deseas eliminar a
                        <strong id="eliminarNombre" class="modal-eliminar-nombre"></strong>?
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


    {{-- Datos iniciales para JS: página de retorno --}}
    <script>
        window.paginaRetorno = {{ session('pagina_retorno', 1) }};
    </script>

    @push('scripts')
        @vite('resources/js/huespedes/index.js')
    @endpush

</x-app-layout>
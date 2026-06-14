<x-app-layout>

    <div class="pagina-contenedor">

        <!-- ENCABEZADO -->
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Huéspedes</h1>
            <button class="btn-primario" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="bi bi-plus-lg"></i> Nuevo Huésped
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
                <label>Nombre</label>
                <div class="campo-input">
                    <i class="bi bi-person campo-icono"></i>
                    <input type="text" id="filtroNombre" placeholder="Ej: Juan">
                </div>
            </div>

            <div class="filtro-grupo">
                <label>Tipo Doc.</label>
                <div class="campo-select">
                    <i class="bi bi-card-text campo-icono"></i>
                    <select id="filtroTipoDoc">
                        <option value="">Todos</option>
                        @foreach($tiposDocumento as $tipo)
                            <option value="{{ $tipo->id }}">{{ strtoupper($tipo->nombre) }}</option>
                        @endforeach
                    </select>
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

        <!-- TABLA HUÉSPEDES -->
        <div class="tabla-contenedor">
            <table class="tabla" id="tablaHuespedes">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo Doc.</th>
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

        <!-- PAGINACIÓN -->
        <div id="paginacion" style="display:none; justify-content:center; margin-top:20px; gap:4px; flex-wrap:wrap;"></div>

    </div>

    <!-- MODAL CREAR HUÉSPED -->
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

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">

                            <div class="campo-grupo">
                                <label>Tipo de Documento</label>
                                <div class="campo-select">
                                    <i class="bi bi-card-text campo-icono"></i>
                                    <select name="tipo_doc_id" id="crearTipoDoc"
                                        onchange="window.verificarDocumento('crearNumDoc', 'crearTipoDoc', 'errorDocCrear')"
                                        required>
                                        <option value="">Seleccionar...</option>
                                        @foreach($tiposDocumento as $tipo)
                                            <option value="{{ $tipo->id }}">{{ strtoupper($tipo->nombre) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="campo-grupo">
                                <label>N° Documento</label>
                                <div class="campo-input">
                                    <i class="bi bi-hash campo-icono"></i>
                                    <input type="text" name="num_doc" id="crearNumDoc"
                                        placeholder="Ej: 12345678"
                                        oninput="window.verificarDocumento('crearNumDoc', 'crearTipoDoc', 'errorDocCrear')"
                                        maxlength="20" required>
                                </div>
                                <small id="errorDocCrear" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                            </div>

                        </div>

                        <div class="campo-grupo">
                            <label>Teléfono <span style="color: var(--gris-texto); font-size:0.75rem;">(opcional)</span></label>
                            <div class="campo-input">
                                <i class="bi bi-telephone campo-icono"></i>
                                <input type="text" name="telefono" id="crearTelefono"
                                    placeholder="Ej: 987654321"
                                    oninput="window.verificarTelefono('crearTelefono', 'errorTelCrear')"
                                    maxlength="15">
                            </div>
                            <small id="errorTelCrear" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
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

    <!-- MODAL EDITAR HUÉSPED -->
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
                        <input type="hidden" id="editId" value="">
                        <input type="hidden" id="editPaginaActual" name="pagina_actual" value="1">
                        <div class="campo-grupo">
                            <label>Nombre completo</label>
                            <div class="campo-input">
                                <i class="bi bi-person campo-icono"></i>
                                <input type="text" name="nombre" id="editNombre" maxlength="100" required>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">

                            <div class="campo-grupo">
                                <label>Tipo de Documento</label>
                                <div class="campo-select">
                                    <i class="bi bi-card-text campo-icono"></i>
                                    <select name="tipo_doc_id" id="editTipoDoc"
                                        onchange="window.verificarDocumento('editNumDoc', 'editTipoDoc', 'errorDocEditar', document.getElementById('editId').value)"
                                        required>
                                        @foreach($tiposDocumento as $tipo)
                                            <option value="{{ $tipo->id }}">{{ strtoupper($tipo->nombre) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="campo-grupo">
                                <label>N° Documento</label>
                                <div class="campo-input">
                                    <i class="bi bi-hash campo-icono"></i>
                                    <input type="text" name="num_doc" id="editNumDoc"
                                        oninput="window.verificarDocumento('editNumDoc', 'editTipoDoc', 'errorDocEditar', document.getElementById('editId').value)"
                                        maxlength="20" required>
                                </div>
                                <small id="errorDocEditar" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                            </div>

                        </div>

                        <div class="campo-grupo">
                            <label>Teléfono <span style="color: var(--gris-texto); font-size:0.75rem;">(opcional)</span></label>
                            <div class="campo-input">
                                <i class="bi bi-telephone campo-icono"></i>
                                <input type="text" name="telefono" id="editTelefono"
                                    oninput="window.verificarTelefono('editTelefono', 'errorTelEditar', document.getElementById('editId').value)"
                                    maxlength="15">
                            </div>
                            <small id="errorTelEditar" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
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

    <!-- MODAL ELIMINAR HUÉSPED -->
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
                    <p style="color: var(--gris-texto); font-size: 0.9rem;">
                        ¿Estás seguro que deseas eliminar a
                        <strong id="eliminarNombre" style="color: var(--azul-marino);"></strong>?
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
        @vite('resources/js/huespedes/index.js')
    @endpush

</x-app-layout>
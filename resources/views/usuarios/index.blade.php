<x-app-layout>

    <div class="pagina-contenedor">

        <!-- ENCABEZADO -->
        <div class="pagina-encabezado">
            <h1 class="pagina-titulo">Usuarios</h1>
            <button class="btn-primario" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="bi bi-plus-lg"></i> Nuevo Usuario
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

        <!-- TABLA -->
        <div class="tabla-contenedor-scroll">
            <div class="tabla-scroll-inner">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $usuario)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $usuario->name }}</td>
                            <td>{{ $usuario->email }}</td>
                            <td>
                                <span class="badge-rol {{ $usuario->rol->nombre === 'gerente' ? 'badge-gerente' : 'badge-recepcionista' }}">
                                    {{ strtoupper($usuario->rol->nombre) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge-estado {{ $usuario->activo ? 'badge-activo' : 'badge-inactivo' }}">
                                    {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="acciones">
                                <button class="btn-accion btn-editar"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditar"
                                    data-id="{{ $usuario->id }}"
                                    data-name="{{ $usuario->name }}"
                                    data-email="{{ $usuario->email }}"
                                    data-rol="{{ $usuario->rol_id }}"
                                    data-activo="{{ $usuario->activo }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-accion btn-eliminar"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEliminar"
                                    data-id="{{ $usuario->id }}"
                                    data-name="{{ $usuario->name }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL CREAR -->
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-person-plus"></i> Nuevo Usuario</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('usuarios.store') }}" id="formCrear"
                    onsubmit="return validarFormulario('formCrear', 'errorEmailCrear', 'errorPasswordCrear')">
                    @csrf
                    <div class="modal-body">

                        @if($errors->any() && !session('editar'))
                            <div class="login-error mb-3">
                                <i class="bi bi-exclamation-circle"></i>
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="campo-grupo">
                            <label>Nombre completo</label>
                            <div class="campo-input">
                                <i class="bi bi-person campo-icono"></i>
                                <input type="text" name="name" placeholder="Nombre del usuario" required>
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>Correo electrónico</label>
                            <div class="campo-input">
                                <i class="bi bi-envelope campo-icono"></i>
                                <input type="email" name="email" id="crearEmail"
                                    placeholder="correo@ejemplo.com"
                                    onblur="verificarEmail(this, 'errorEmailCrear')"
                                    required>
                            </div>
                            <small id="errorEmailCrear" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Contraseña</label>
                            <div class="campo-password">
                                <i class="bi bi-lock campo-icono"></i>
                                <input type="password" name="password" id="crearPassword"
                                    placeholder="••••••••"
                                    oninput="verificarPassword(this, 'errorPasswordCrear')"
                                    required>
                                <button type="button" class="btn-ojito" onclick="togglePassword('crearPassword', 'ojitoCear')">
                                    <i class="bi bi-eye" id="ojitoCear"></i>
                                </button>
                            </div>
                            <small id="errorPasswordCrear" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Rol</label>
                            <div class="campo-select">
                                <i class="bi bi-shield campo-icono"></i>
                                <select name="rol_id" required>
                                    @foreach($roles as $rol)
                                        <option value="{{ $rol->id }}">{{ ucfirst($rol->nombre) }}</option>
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

    <!-- MODAL EDITAR -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-pencil-square"></i> Editar Usuario</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="" id="formEditar"
                    onsubmit="return validarFormulario('formEditar', 'errorEmailEditar', 'errorPasswordEditar')">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <input type="hidden" id="editUsuarioId" value="">

                        @if($errors->any() && session('editar'))
                            <div class="login-error mb-3">
                                <i class="bi bi-exclamation-circle"></i>
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="campo-grupo">
                            <label>Nombre completo</label>
                            <div class="campo-input">
                                <i class="bi bi-person campo-icono"></i>
                                <input type="text" name="name" id="editNombre" placeholder="Nombre del usuario" required>
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>Correo electrónico</label>
                            <div class="campo-input {{ $errors->has('email') && session('editar') ? 'error' : '' }}">
                                <i class="bi bi-envelope campo-icono"></i>
                                <input type="email" name="email" id="editEmail"
                                    placeholder="correo@ejemplo.com"
                                    onblur="verificarEmail(this, 'errorEmailEditar', document.getElementById('editUsuarioId').value)"
                                    required>
                            </div>
                            <small id="errorEmailEditar" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Nueva contraseña <span style="color: var(--gris-texto); font-size:0.75rem;">(dejar vacío para no cambiar)</span></label>
                            <div class="campo-password">
                                <i class="bi bi-lock campo-icono"></i>
                                <input type="password" name="password" id="editPassword"
                                    placeholder="••••••••"
                                    oninput="verificarPassword(this, 'errorPasswordEditar', false)">
                                <button type="button" class="btn-ojito" onclick="togglePassword('editPassword', 'ojitoEdit')">
                                    <i class="bi bi-eye" id="ojitoEdit"></i>
                                </button>
                            </div>
                            <small id="errorPasswordEditar" style="color:#cc0000; font-size:0.78rem; margin-top:4px; display:block;"></small>
                        </div>

                        <div class="campo-grupo">
                            <label>Rol</label>
                            <div class="campo-select">
                                <i class="bi bi-shield campo-icono"></i>
                                <select name="rol_id" id="editRol" required>
                                    @foreach($roles as $rol)
                                        <option value="{{ $rol->id }}">{{ ucfirst($rol->nombre) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="campo-grupo">
                            <label>Estado</label>
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

    <!-- MODAL ELIMINAR -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-california">
                <div class="modal-header">
                    <h5 class="modal-titulo"><i class="bi bi-exclamation-triangle"></i> Eliminar Usuario</h5>
                    <button type="button" class="btn-cerrar-modal" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p style="color: var(--gris-texto); font-size: 0.9rem;">
                        ¿Estás seguro que deseas eliminar al usuario
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

    @push('scripts')
        @vite('resources/js/usuarios/index.js')
    @endpush

</x-app-layout>
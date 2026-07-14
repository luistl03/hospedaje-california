// Consulta si el número de habitación ya existe y marca error visual
window.verificarNumero = function (input, errorId, numeroOriginal = 0) {
    const numero     = input.value;
    const errorEl    = document.getElementById(errorId);
    const campoInput = input.closest('.campo-input');

    if (!numero) {
        errorEl.textContent = '';
        campoInput.classList.remove('error');
        return;
    }

    fetch(`/habitaciones/verificar-numero?numero=${numero}&numero_original=${numeroOriginal}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Este número de habitación ya existe.';
                campoInput.classList.add('error');
            } else {
                errorEl.textContent = '';
                campoInput.classList.remove('error');
            }
        });
};

// Consulta si el nombre del tipo ya existe y marca error visual
window.verificarNombreTipo = function (input, errorId, tipoId = 0) {
    const nombre     = input.value;
    const errorEl    = document.getElementById(errorId);
    const campoInput = input.closest('.campo-input');

    if (!nombre) {
        errorEl.textContent = '';
        campoInput.classList.remove('error');
        return;
    }

    fetch(`/tipos-habitacion/verificar-nombre?nombre=${encodeURIComponent(nombre)}&id=${tipoId}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Ya existe un tipo con ese nombre.';
                campoInput.classList.add('error');
            } else {
                errorEl.textContent = '';
                campoInput.classList.remove('error');
            }
        });
};

// Permite solo enteros positivos (mínimo 1) en el input
window.formatearEnteroPositivo = function (input) {
    let val = input.value.replace(/[^0-9]/g, '');

    if (val === '' || parseInt(val, 10) < 1) {
        val = '1';
    } else {
        val = String(parseInt(val, 10));
    }

    input.value = val;
};

// Bloquea el submit si hay un error de número visible
window.validarFormularioHabitacion = function (errorNumeroId) {
    return document.getElementById(errorNumeroId).textContent.trim() === '';
};

// Reactiva el select de tipo (estaba disabled solo visualmente) antes de enviar
window.prepararEnvioEditar = function (errorNumeroId) {
    document.getElementById('editTipo').disabled   = false;
    document.getElementById('editActivo').disabled = false;
    return window.validarFormularioHabitacion(errorNumeroId);
};

// Bloquea el submit si hay un error de nombre visible
window.validarFormularioTipo = function (errorNombreId) {
    return document.getElementById(errorNombreId).textContent.trim() === '';
};

// Consulta habitaciones filtradas (AJAX) y pinta la tabla
let paginaActual = 1;

window.buscarHabitaciones = function (pagina = 1) {
    paginaActual = pagina;

    const params = new URLSearchParams({
        numero:    document.getElementById('filtroNumero').value,
        piso:      document.getElementById('filtroPiso').value,
        tipo_id:   document.getElementById('filtroTipo').value,
        estado_id: document.getElementById('filtroEstado').value,
        activo:    document.getElementById('filtroActivo').value,
        pagina:    pagina,
    });

    fetch(`/habitaciones/filtrar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
        .then(res => res.json())
        .then(resp => {
            const tbody = document.querySelector('#tablaHabitaciones tbody');
            tbody.innerHTML = '';

            // Sin resultados: estado vacío
            if (resp.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="paso2-estado">
                            <i class="bi bi-search"></i>
                            No se encontraron habitaciones con los filtros aplicados.
                        </td>
                    </tr>`;
                document.getElementById('paginacion').classList.remove('activo');
                return;
            }

            // Mapeo de estado de habitación → clase de badge
            const estadoBadge = {
                disponible:    'badge-estado-disponible',
                ocupada:       'badge-estado-ocupada',
                mantenimiento: 'badge-estado-mantenimiento',
                limpieza:      'badge-estado-limpieza',
                reservada:     'badge-estado-reservada',
            };

            resp.data.forEach(h => {
                const bloqueadaEdicion    = h.tiene_reserva_activa;
                const bloqueadaEliminacion = h.tiene_historial;
                const esGerente            = window.rolUsuario === 'gerente';

                const btnEditar = bloqueadaEdicion
                    ? `<button class="btn-accion btn-editar" disabled title="No se puede editar: la habitación está ocupada (reserva activa)">
                        <i class="bi bi-pencil"></i>
                    </button>`
                    : `<button class="btn-accion btn-editar"
                        data-bs-toggle="modal" data-bs-target="#modalEditar"
                        data-numero="${h.numero}"
                        data-tipo="${h.tipo_id}"
                        data-estado-id="${h.estado_id}"
                        data-activo="${h.activo}"
                        data-tiene-historial="${h.tiene_historial ? 1 : 0}"
                        data-tiene-reserva-pendiente="${h.tiene_reserva_pendiente ? 1 : 0}">
                        <i class="bi bi-pencil"></i>
                    </button>`;

                let btnEliminar = '';
                if (esGerente) {
                    btnEliminar = bloqueadaEliminacion
                        ? `<button class="btn-accion btn-eliminar" disabled title="No se puede eliminar: tiene historial de reservas. Puede desactivarla.">
                            <i class="bi bi-trash"></i>
                        </button>`
                        : `<button class="btn-accion btn-eliminar"
                            data-bs-toggle="modal" data-bs-target="#modalEliminar"
                            data-numero="${h.numero}">
                            <i class="bi bi-trash"></i>
                        </button>`;
                }

                tbody.innerHTML += `
                    <tr>
                        <td><strong>${h.numero}</strong></td>
                        <td>${h.tipo_nombre}</td>
                        <td>S/ ${h.precio_hora}</td>
                        <td>S/ ${h.precio_noche}</td>
                        <td><span class="badge-estado ${estadoBadge[h.estado_nombre]}">${h.estado_nombre.charAt(0).toUpperCase() + h.estado_nombre.slice(1)}</span></td>
                        <td><span class="badge-estado ${h.activo ? 'badge-activo' : 'badge-inactivo'}">${h.activo ? 'Activo' : 'Inactivo'}</span></td>
                        <td class="acciones">
                            ${btnEditar}
                            ${btnEliminar}
                        </td>
                    </tr>`;
            });

            renderizarPaginacion(resp.pagina_actual, resp.total_paginas);
        });
};

// Resetea los filtros y vuelve a buscar desde la página 1
window.limpiarFiltros = function () {
    document.getElementById('filtroNumero').value = '';
    document.getElementById('filtroPiso').value   = '';
    document.getElementById('filtroTipo').value   = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroActivo').value = '';
    window.buscarHabitaciones(1);
};

// Genera los botones de paginación según página actual y total
function renderizarPaginacion(actual, total) {
    const contenedor = document.getElementById('paginacion');

    if (total <= 1) {
        contenedor.classList.remove('activo');
        return;
    }

    contenedor.classList.add('activo');
    contenedor.innerHTML = '';

    const btn = (i, label = null, disabled = false, activo = false) => `
        <button class="btn-pagina ${activo ? 'btn-pagina-activo' : ''} ${disabled ? 'btn-pagina-disabled' : ''}"
            onclick="window.buscarHabitaciones(${i})" ${disabled ? 'disabled' : ''}>
            ${label ?? i}
        </button>`;

    const puntos = `<span class="paginacion-puntos">...</span>`;

    contenedor.innerHTML += btn(actual - 1, '<i class="bi bi-chevron-left"></i>', actual === 1);
    contenedor.innerHTML += btn(1, null, false, actual === 1);

    if (actual > 4) contenedor.innerHTML += puntos;

    for (let i = Math.max(2, actual - 2); i <= Math.min(total - 1, actual + 2); i++) {
        contenedor.innerHTML += btn(i, null, false, i === actual);
    }

    if (actual < total - 3) contenedor.innerHTML += puntos;

    if (total > 1) contenedor.innerHTML += btn(total, null, false, actual === total);

    contenedor.innerHTML += btn(actual + 1, '<i class="bi bi-chevron-right"></i>', actual === total);
}

// Listeners de modales: cargan datos al abrir, limpian al cerrar
document.addEventListener('DOMContentLoaded', () => {

    // Modal Editar Habitación: carga datos y bloquea número/tipo si tiene historial
    document.getElementById('modalEditar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editNumero').value         = btn.dataset.numero;
        document.getElementById('editNumeroOriginal').value = btn.dataset.numero;
        document.getElementById('editTipo').value           = btn.dataset.tipo;
        document.getElementById('editEstado').value         = btn.dataset.estadoId;
        document.getElementById('editActivo').value         = btn.dataset.activo;
        document.getElementById('editPaginaActual').value   = paginaActual;
        document.getElementById('formEditar').action        = '/habitaciones/' + btn.dataset.numero;

        const tieneHistorial  = btn.dataset.tieneHistorial === '1';
        const tienePendiente  = btn.dataset.tieneReservaPendiente === '1';

        const numeroInput   = document.getElementById('editNumero');
        const tipoSelect     = document.getElementById('editTipo');
        const activoSelect   = document.getElementById('editActivo');
        const estadoSelect   = document.getElementById('editEstado');
        const avisoEl        = document.getElementById('editAvisoHistorial');

        // Numero y tipo se bloquean si hay historial O reserva pendiente
        numeroInput.readOnly = tieneHistorial || tienePendiente;
        tipoSelect.disabled  = tieneHistorial || tienePendiente;

        // Activo solo se bloquea (forzado a "Activo") si hay reserva pendiente
        activoSelect.disabled = tienePendiente;
        if (tienePendiente) {
            activoSelect.value = '1';
        }

        // Estado: si hay pendiente, solo se puede elegir limpieza o mantenimiento
        [...estadoSelect.options].forEach(opt => {
            opt.hidden = tienePendiente && !['disponible', 'limpieza', 'mantenimiento'].includes(opt.dataset.nombreEstado);
        });

        if (tienePendiente) {
            avisoEl.className = 'aviso-franja franja-info';
            avisoEl.innerHTML = '<i class="bi bi-info-circle-fill"></i> Esta habitación tiene una reserva próxima: solo puedes cambiar su estado a Disponible, Limpieza o Mantenimiento, sin cambiar número, tipo, ni desactivarla.';
            avisoEl.classList.remove('d-none');
        } else if (tieneHistorial) {
            pintarAvisoHistorial(avisoEl);
        } else {
            avisoEl.classList.add('d-none');
        }
    });

    // Pinta el aviso de historial reutilizando el estilo de .aviso-franja
    function pintarAvisoHistorial(elemento) {
        elemento.className = 'aviso-franja franja-info';
        elemento.innerHTML = '<i class="bi bi-info-circle-fill"></i> Esta habitación tiene historial de reservas: no se puede cambiar su número ni su tipo. Solo puede actualizar su estado o desactivarla.';
        elemento.classList.remove('d-none');
    }

    // Modal Editar Habitación: limpia campos, errores y bloqueos al cerrar
    document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditar').reset();
        document.getElementById('errorNumeroEditar').textContent = '';
        document.querySelector('#modalEditar .campo-input').classList.remove('error');
        document.getElementById('editNumero').readOnly = false;
        document.getElementById('editTipo').disabled    = false;
        document.getElementById('editActivo').disabled  = false;
        document.querySelectorAll('#editEstado option').forEach(opt => opt.hidden = false);
        document.getElementById('editAvisoHistorial').classList.add('d-none');
    });
    // Modal Crear Habitación: limpia campos y errores al cerrar
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        document.getElementById('errorNumeroCrear').textContent = '';
        document.querySelector('#modalCrear .campo-input').classList.remove('error');
    });

    // Modal Eliminar Habitación: carga número y acción del form al abrir
    document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarNumero').textContent = 'N° ' + btn.dataset.numero;
        document.getElementById('formEliminar').action        = '/habitaciones/' + btn.dataset.numero;
    });

    // Modal Editar Tipo: carga datos al abrir, limpia al cerrar
    const modalEditarTipo = document.getElementById('modalEditarTipo');
    if (modalEditarTipo) {
        modalEditarTipo.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('editTipoId').value          = btn.dataset.id;
            document.getElementById('editTipoNombre').value      = btn.dataset.nombre;
            document.getElementById('editTipoPrecioHora').value  = btn.dataset.precioHora;
            document.getElementById('editTipoPrecioNoche').value = btn.dataset.precioNoche;
            document.getElementById('editMaxHuespedes').value    = btn.dataset.maxHuespedes ?? 1;
            document.getElementById('editTipoDescripcion').value = btn.dataset.descripcion ?? '';
            document.getElementById('editTipoActivo').value      = btn.dataset.activo;
            document.getElementById('formEditarTipo').action     = '/tipos-habitacion/' + btn.dataset.id;
        });

        modalEditarTipo.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formEditarTipo').reset();
            document.getElementById('errorNombreTipoEditar').textContent = '';
            document.querySelector('#modalEditarTipo .campo-input').classList.remove('error');
        });
    }

    // Modal Gestionar Tipos: quita alerta de error al abrir, limpia form al cerrar
    const modalTipos = document.getElementById('modalTipos');
    if (modalTipos) {
        modalTipos.addEventListener('show.bs.modal', function () {
            const errorEl = document.querySelector('#modalTipos .login-error');
            if (errorEl) errorEl.remove();
        });

        modalTipos.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formCrearTipo').reset();
            document.getElementById('errorNombreTipoCrear').textContent = '';
            document.querySelector('#modalTipos .campo-input').classList.remove('error');
            document.getElementById('crearMaxHuespedes').value = '1';
        });
    }

    // Modal Eliminar Tipo: carga nombre y acción del form al abrir
    const modalEliminarTipo = document.getElementById('modalEliminarTipo');
    if (modalEliminarTipo) {
        modalEliminarTipo.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('eliminarTipoNombre').textContent = btn.dataset.nombre;
            document.getElementById('formEliminarTipo').action        = '/tipos-habitacion/' + btn.dataset.id;
        });
    }

    // Carga inicial: restaura la página guardada en sesión
    const paginaInicial = window.paginaRetorno ?? 1;
    window.buscarHabitaciones(paginaInicial);
});
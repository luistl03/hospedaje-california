// ─── VERIFICAR NÚMERO DE HABITACIÓN (AJAX) ───
window.verificarNumero = function (input, errorId, numeroOriginal = 0) {
    const numero  = input.value;
    const errorEl = document.getElementById(errorId);

    if (!numero) {
        errorEl.textContent = '';
        return;
    }

    fetch(`/habitaciones/verificar-numero?numero=${numero}&numero_original=${numeroOriginal}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(res => res.json())
    .then(data => {
        if (data.existe) {
            errorEl.textContent = 'Este número de habitación ya existe.';
            input.closest('.campo-input').style.borderBottomColor = '#cc0000';
        } else {
            errorEl.textContent = '';
            input.closest('.campo-input').style.borderBottomColor = '';
        }
    });
};

// ─── VERIFICAR NOMBRE DE TIPO (AJAX) ───
window.verificarNombreTipo = function (input, errorId, tipoId = 0) {
    const nombre  = input.value;
    const errorEl = document.getElementById(errorId);

    if (!nombre) {
        errorEl.textContent = '';
        return;
    }

    fetch(`/tipos-habitacion/verificar-nombre?nombre=${encodeURIComponent(nombre)}&id=${tipoId}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(res => res.json())
    .then(data => {
        if (data.existe) {
            errorEl.textContent = 'Ya existe un tipo con ese nombre.';
            input.closest('.campo-input').style.borderBottomColor = '#cc0000';
        } else {
            errorEl.textContent = '';
            input.closest('.campo-input').style.borderBottomColor = '';
        }
    });
};

// ─── SANITIZAR INPUT: solo enteros positivos, mínimo 1 ───
window.formatearEnteroPositivo = function (input) {
    // Elimina todo lo que no sea dígito
    let val = input.value.replace(/[^0-9]/g, '');

    // Si queda vacío o es 0, fuerza a 1
    if (val === '' || parseInt(val, 10) < 1) {
        val = '1';
    } else {
        // Elimina ceros a la izquierda
        val = String(parseInt(val, 10));
    }

    input.value = val;
};

// ─── VALIDAR FORMULARIO HABITACIÓN ───
window.validarFormularioHabitacion = function (errorNumeroId) {
    return document.getElementById(errorNumeroId).textContent.trim() === '';
};

// ─── VALIDAR FORMULARIO TIPO ───
window.validarFormularioTipo = function (errorNombreId) {
    return document.getElementById(errorNombreId).textContent.trim() === '';
};

// ─── FILTROS ───
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

        if (resp.data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px 20px; color:var(--gris-texto);">
                        <i class="bi bi-search" style="font-size:1.5rem; display:block; margin-bottom:8px; opacity:0.4;"></i>
                        No se encontraron habitaciones con los filtros aplicados.
                    </td>
                </tr>`;
            document.getElementById('paginacion').style.display = 'none';
            return;
        }

        const estadoBadge = {
            disponible:    'badge-estado-disponible',
            ocupada:       'badge-estado-ocupada',
            mantenimiento: 'badge-estado-mantenimiento',
            limpieza:      'badge-estado-limpieza',
            reservada:     'badge-estado-reservada',
        };

        resp.data.forEach(h => {
            tbody.innerHTML += `
                <tr>
                    <td><strong>${h.numero}</strong></td>
                    <td>${h.tipo_nombre}</td>
                    <td>S/ ${h.precio_hora}</td>
                    <td>S/ ${h.precio_noche}</td>
                    <td><span class="badge-estado ${estadoBadge[h.estado_nombre]}">${h.estado_nombre.charAt(0).toUpperCase() + h.estado_nombre.slice(1)}</span></td>
                    <td><span class="badge-estado ${h.activo ? 'badge-activo' : 'badge-inactivo'}">${h.activo ? 'Activo' : 'Inactivo'}</span></td>
                    <td class="acciones">
                        <button class="btn-accion btn-editar"
                            data-bs-toggle="modal" data-bs-target="#modalEditar"
                            data-numero="${h.numero}"
                            data-tipo="${h.tipo_id}"
                            data-estado-id="${h.estado_id}"
                            data-activo="${h.activo}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn-accion btn-eliminar"
                            data-bs-toggle="modal" data-bs-target="#modalEliminar"
                            data-numero="${h.numero}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`;
        });

        renderizarPaginacion(resp.pagina_actual, resp.total_paginas);
    });
};

// ─── LIMPIAR FILTROS ───
window.limpiarFiltros = function () {
    document.getElementById('filtroNumero').value = '';
    document.getElementById('filtroPiso').value   = '';
    document.getElementById('filtroTipo').value   = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroActivo').value = '';
    window.buscarHabitaciones(1);
};

// ─── PAGINACIÓN ───
function renderizarPaginacion(actual, total) {
    const contenedor = document.getElementById('paginacion');

    if (total <= 1) {
        contenedor.style.display = 'none';
        return;
    }

    contenedor.style.display = 'flex';
    contenedor.innerHTML = '';

    const btn = (i, label = null, disabled = false, activo = false) => `
        <button class="btn-pagina ${activo ? 'btn-pagina-activo' : ''} ${disabled ? 'btn-pagina-disabled' : ''}"
            onclick="window.buscarHabitaciones(${i})" ${disabled ? 'disabled' : ''}>
            ${label ?? i}
        </button>`;

    const puntos = `<span style="padding:0 6px; color:var(--gris-texto); align-self:center;">...</span>`;

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

// ─── MODALES ───
document.addEventListener('DOMContentLoaded', () => {
    // Modal editar habitación — cargar datos
    document.getElementById('modalEditar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editNumero').value         = btn.dataset.numero;
        document.getElementById('editNumeroOriginal').value = btn.dataset.numero;
        document.getElementById('editTipo').value           = btn.dataset.tipo;
        document.getElementById('editEstado').value         = btn.dataset.estadoId;
        document.getElementById('editActivo').value         = btn.dataset.activo;
        document.getElementById('editPaginaActual').value   = paginaActual; // ← AGREGAR
        document.getElementById('formEditar').action        = '/habitaciones/' + btn.dataset.numero;
    });

    // Modal editar habitación — limpiar al cerrar
    document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditar').reset();
        document.getElementById('errorNumeroEditar').textContent = '';
        document.querySelector('#modalEditar .campo-input').style.borderBottomColor = '';
    });

    // Modal crear habitación — limpiar al cerrar
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        document.getElementById('errorNumeroCrear').textContent = '';
        document.querySelector('#modalCrear .campo-input').style.borderBottomColor = '';
    });

    // Modal eliminar habitación — cargar datos
    document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarNumero').textContent = 'N° ' + btn.dataset.numero;
        document.getElementById('formEliminar').action        = '/habitaciones/' + btn.dataset.numero;
    });

    // Modal editar tipo — cargar datos
    document.getElementById('modalEditarTipo').addEventListener('show.bs.modal', function (e) {
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

    // Modal editar tipo — limpiar al cerrar
    document.getElementById('modalEditarTipo').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditarTipo').reset();
        document.getElementById('errorNombreTipoEditar').textContent = '';
        document.querySelector('#modalEditarTipo .campo-input').style.borderBottomColor = '';
    });

    // Modal crear tipo — limpiar al cerrar
    document.getElementById('modalTipos').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrearTipo').reset();
        document.getElementById('errorNombreTipoCrear').textContent = '';
        document.querySelector('#modalTipos .campo-input').style.borderBottomColor = '';
        document.getElementById('crearMaxHuespedes').value = '1';
    });

    // Modal eliminar tipo — cargar datos
    document.getElementById('modalEliminarTipo').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarTipoNombre').textContent = btn.dataset.nombre;
        document.getElementById('formEliminarTipo').action        = '/tipos-habitacion/' + btn.dataset.id;
    });

    // Modal tipos — limpiar error al abrir
    document.getElementById('modalTipos').addEventListener('show.bs.modal', function () {
        const errorEl = document.querySelector('#modalTipos .login-error');
        if (errorEl) errorEl.remove();
    });

    // ─── CARGA INICIAL ───
    const paginaInicial = window.paginaRetorno ?? 1;
    window.buscarHabitaciones(paginaInicial);
});
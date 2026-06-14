// ─── VERIFICAR DOCUMENTO (AJAX) ───
window.verificarDocumento = function (numDocId, tipoDocId, errorId, huespedId = 0) {
    const numDoc  = document.getElementById(numDocId).value;
    const tipoDoc = document.getElementById(tipoDocId).value;
    const errorEl = document.getElementById(errorId);

    if (!numDoc || !tipoDoc) {
        errorEl.textContent = '';
        return;
    }

    fetch(`/huespedes/verificar-documento?num_doc=${encodeURIComponent(numDoc)}&tipo_doc_id=${tipoDoc}&id=${huespedId}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Este documento ya está registrado.';
                document.getElementById(numDocId).closest('.campo-input').style.borderBottomColor = '#cc0000';
            } else {
                errorEl.textContent = '';
                document.getElementById(numDocId).closest('.campo-input').style.borderBottomColor = '';
            }
        });
};

// ─── VERIFICAR TELÉFONO (AJAX) ───
window.verificarTelefono = function (telId, errorId, huespedId = 0) {
    const telefono = document.getElementById(telId).value;
    const errorEl  = document.getElementById(errorId);

    if (!telefono) {
        errorEl.textContent = '';
        document.getElementById(telId).closest('.campo-input').style.borderBottomColor = '';
        return;
    }

    fetch(`/huespedes/verificar-telefono?telefono=${encodeURIComponent(telefono)}&id=${huespedId}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Este teléfono ya está registrado.';
                document.getElementById(telId).closest('.campo-input').style.borderBottomColor = '#cc0000';
            } else {
                errorEl.textContent = '';
                document.getElementById(telId).closest('.campo-input').style.borderBottomColor = '';
            }
        });
};

// ─── VALIDAR FORMULARIO ───
window.validarFormulario = function (errorDocId, errorTelId) {
    const errorDoc = document.getElementById(errorDocId);
    const errorTel = document.getElementById(errorTelId);
    return errorDoc.textContent.trim() === '' && errorTel.textContent.trim() === '';
};

// ─── FILTROS ───
let paginaActual = 1;

window.buscarHuespedes = function (pagina = 1) {
    paginaActual = pagina;

    const params = new URLSearchParams({
        nombre:      document.getElementById('filtroNombre').value,
        tipo_doc_id: document.getElementById('filtroTipoDoc').value,
        num_doc:     document.getElementById('filtroNumDoc').value,
        telefono:    document.getElementById('filtroTelefono').value,
        activo:      document.getElementById('filtroActivo').value,
        pagina,
    });

    fetch(`/huespedes/filtrar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(resp => {
            const tbody = document.querySelector('#tablaHuespedes tbody');
            tbody.innerHTML = '';

            if (resp.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px 20px; color:var(--gris-texto);">
                            <i class="bi bi-search" style="font-size:1.5rem; display:block; margin-bottom:8px; opacity:0.4;"></i>
                            No se encontraron huéspedes con los filtros aplicados.
                        </td>
                    </tr>`;
                document.getElementById('paginacion').style.display = 'none';
                return;
            }

            resp.data.forEach(h => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${h.nombre}</strong></td>
                        <td>${h.tipo_doc.toUpperCase()}</td>
                        <td>${h.num_doc}</td>
                        <td>${h.telefono}</td>
                        <td>
                            <span class="badge-estado ${h.activo ? 'badge-activo' : 'badge-inactivo'}">
                                ${h.activo ? 'Activo' : 'Inactivo'}
                            </span>
                        </td>
                        <td class="acciones">
                            <button class="btn-accion btn-editar"
                                data-bs-toggle="modal" data-bs-target="#modalEditar"
                                data-id="${h.id}"
                                data-nombre="${h.nombre}"
                                data-tipo-doc-id="${h.tipo_doc_id}"
                                data-num-doc="${h.num_doc}"
                                data-telefono="${h.telefono === '—' ? '' : h.telefono}"
                                data-activo="${h.activo}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-accion btn-eliminar"
                                data-bs-toggle="modal" data-bs-target="#modalEliminar"
                                data-id="${h.id}"
                                data-nombre="${h.nombre}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
            });

            renderizarPaginacion(resp.pagina_actual, resp.total_paginas);
        });
};

window.limpiarFiltros = function () {
    document.getElementById('filtroNombre').value   = '';
    document.getElementById('filtroTipoDoc').value  = '';
    document.getElementById('filtroNumDoc').value   = '';
    document.getElementById('filtroTelefono').value = '';
    document.getElementById('filtroActivo').value   = '';
    window.buscarHuespedes(1);
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
            onclick="window.buscarHuespedes(${i})" ${disabled ? 'disabled' : ''}>
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

    // ─── BLOQUEAR N° DOC HASTA SELECCIONAR TIPO (CREAR) ───
    const crearTipoDoc = document.getElementById('crearTipoDoc');
    const crearNumDoc  = document.getElementById('crearNumDoc');

    crearNumDoc.disabled     = true;
    crearNumDoc.placeholder  = 'Primero selecciona el tipo';

    crearTipoDoc.addEventListener('change', function () {
        const habilitado        = this.value !== '';
        crearNumDoc.disabled    = !habilitado;
        crearNumDoc.placeholder = habilitado ? 'Ej: 12345678' : 'Primero selecciona el tipo';
        if (!habilitado) {
            crearNumDoc.value = '';
            document.getElementById('errorDocCrear').textContent = '';
            crearNumDoc.closest('.campo-input').style.borderBottomColor = '';
        }
    });

    // ─── BLOQUEAR N° DOC SI SE LIMPIA TIPO (EDITAR) ───
    document.getElementById('editTipoDoc').addEventListener('change', function () {
        const editNumDoc = document.getElementById('editNumDoc');
        if (this.value === '') {
            editNumDoc.disabled = true;
            editNumDoc.value    = '';
            document.getElementById('errorDocEditar').textContent = '';
            editNumDoc.closest('.campo-input').style.borderBottomColor = '';
        } else {
            editNumDoc.disabled = false;
        }
    });

    // ─── MODAL CREAR — limpiar al cerrar ───
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        document.getElementById('errorDocCrear').textContent = '';
        document.getElementById('errorTelCrear').textContent = '';
        document.getElementById('crearNumDoc').closest('.campo-input').style.borderBottomColor   = '';
        document.getElementById('crearTelefono').closest('.campo-input').style.borderBottomColor = '';
        crearNumDoc.disabled    = true;
        crearNumDoc.placeholder = 'Primero selecciona el tipo';
    });

    // ─── MODAL EDITAR — cargar datos ───
    document.getElementById('modalEditar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editId').value           = btn.dataset.id;
        document.getElementById('editNombre').value       = btn.dataset.nombre;
        document.getElementById('editTipoDoc').value      = btn.dataset.tipoDocId;
        document.getElementById('editNumDoc').value       = btn.dataset.numDoc;
        document.getElementById('editNumDoc').disabled    = false;
        document.getElementById('editTelefono').value     = btn.dataset.telefono ?? '';
        document.getElementById('editActivo').value       = String(btn.dataset.activo);
        document.getElementById('editPaginaActual').value = paginaActual;
        document.getElementById('formEditar').action      = `/huespedes/${btn.dataset.id}`;
    });

    // ─── MODAL EDITAR — limpiar al cerrar ───
    document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditar').reset();
        document.getElementById('errorDocEditar').textContent = '';
        document.getElementById('errorTelEditar').textContent = '';
        document.getElementById('editNumDoc').closest('.campo-input').style.borderBottomColor   = '';
        document.getElementById('editTelefono').closest('.campo-input').style.borderBottomColor = '';
    });

    // ─── MODAL ELIMINAR — cargar datos ───
    document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarNombre').textContent = btn.dataset.nombre;
        document.getElementById('formEliminar').action        = `/huespedes/${btn.dataset.id}`;
    });

    // ─── CARGA INICIAL ───
    const paginaInicial = window.paginaRetorno ?? 1;
    window.buscarHuespedes(paginaInicial);
});
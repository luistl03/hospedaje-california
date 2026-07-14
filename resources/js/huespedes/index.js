// Consulta si el documento ya existe y marca error visual
window.verificarDocumento = function (numDocId, errorId, numDocOriginal = '') {
    const numDoc     = document.getElementById(numDocId).value;
    const errorEl    = document.getElementById(errorId);
    const campoInput = document.getElementById(numDocId).closest('.campo-input');

    if (!numDoc) {
        errorEl.textContent = '';
        campoInput.classList.remove('error');
        return;
    }

    fetch(`/huespedes/verificar-documento?num_doc=${encodeURIComponent(numDoc)}&num_doc_original=${encodeURIComponent(numDocOriginal)}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Este documento ya está registrado.';
                campoInput.classList.add('error');
            } else {
                errorEl.textContent = '';
                campoInput.classList.remove('error');
            }
        });
};

// Consulta si el teléfono ya existe y marca error visual
window.verificarTelefono = function (telId, errorId, numDocOriginal = '') {
    const telefono   = document.getElementById(telId).value;
    const errorEl    = document.getElementById(errorId);
    const campoInput = document.getElementById(telId).closest('.campo-input');

    if (!telefono) {
        errorEl.textContent = '';
        campoInput.classList.remove('error');
        return;
    }

    fetch(`/huespedes/verificar-telefono?telefono=${encodeURIComponent(telefono)}&num_doc_original=${encodeURIComponent(numDocOriginal)}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Este teléfono ya está registrado.';
                campoInput.classList.add('error');
            } else {
                errorEl.textContent = '';
                campoInput.classList.remove('error');
            }
        });
};

// Bloquea el submit si hay errores de documento o teléfono visibles
window.validarFormulario = function (errorDocId, errorTelId) {
    const errorDoc = document.getElementById(errorDocId);
    const errorTel = document.getElementById(errorTelId);
    return errorDoc.textContent.trim() === '' && errorTel.textContent.trim() === '';
};

// Consulta huéspedes filtrados (AJAX) y pinta la tabla
let paginaActual = 1;

window.buscarHuespedes = function (pagina = 1) {
    paginaActual = pagina;

    const params = new URLSearchParams({
        nombre:   document.getElementById('filtroNombre').value,
        num_doc:  document.getElementById('filtroNumDoc').value,
        telefono: document.getElementById('filtroTelefono').value,
        activo:   document.getElementById('filtroActivo').value,
        pagina,
    });

    fetch(`/huespedes/filtrar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(resp => {
            const tbody = document.querySelector('#tablaHuespedes tbody');
            tbody.innerHTML = '';

            // Sin resultados: estado vacío
            if (resp.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="paso2-estado">
                            <i class="bi bi-search"></i>
                            No se encontraron huéspedes con los filtros aplicados.
                        </td>
                    </tr>`;
                document.getElementById('paginacion').classList.remove('activo');
                return;
            }

            resp.data.forEach(h => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${h.nombre}</strong></td>
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
                                data-num-doc="${h.num_doc}"
                                data-nombre="${h.nombre}"
                                data-telefono="${h.telefono === '—' ? '' : h.telefono}"
                                data-activo="${h.activo}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-accion btn-eliminar"
                                data-bs-toggle="modal" data-bs-target="#modalEliminar"
                                data-num-doc="${h.num_doc}"
                                data-nombre="${h.nombre}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
            });

            renderizarPaginacion(resp.pagina_actual, resp.total_paginas);
        });
};

// Resetea los filtros y vuelve a buscar desde la página 1
window.limpiarFiltros = function () {
    document.getElementById('filtroNombre').value   = '';
    document.getElementById('filtroNumDoc').value   = '';
    document.getElementById('filtroTelefono').value = '';
    document.getElementById('filtroActivo').value   = '';
    window.buscarHuespedes(1);
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
            onclick="window.buscarHuespedes(${i})" ${disabled ? 'disabled' : ''}>
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

    // Modal Crear Huésped: limpia campos y errores al cerrar
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        document.getElementById('errorDocCrear').textContent = '';
        document.getElementById('errorTelCrear').textContent = '';
        document.getElementById('crearNumDoc').closest('.campo-input').classList.remove('error');
        document.getElementById('crearTelefono').closest('.campo-input').classList.remove('error');
    });

    // Modal Editar Huésped: carga datos al abrir
    document.getElementById('modalEditar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editNumDocOriginal').value = btn.dataset.numDoc;
        document.getElementById('editNombre').value         = btn.dataset.nombre;
        document.getElementById('editNumDoc').value         = btn.dataset.numDoc;
        document.getElementById('editTelefono').value       = btn.dataset.telefono ?? '';
        document.getElementById('editActivo').value         = String(btn.dataset.activo);
        document.getElementById('editPaginaActual').value   = paginaActual;
        document.getElementById('formEditar').action        = `/huespedes/${encodeURIComponent(btn.dataset.numDoc)}`;
    });

    // Modal Editar Huésped: limpia campos y errores al cerrar
    document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditar').reset();
        document.getElementById('errorDocEditar').textContent = '';
        document.getElementById('errorTelEditar').textContent = '';
        document.getElementById('editNumDoc').closest('.campo-input').classList.remove('error');
        document.getElementById('editTelefono').closest('.campo-input').classList.remove('error');
    });

    // Modal Eliminar Huésped: carga nombre y acción del form al abrir
    document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarNombre').textContent = btn.dataset.nombre;
        document.getElementById('formEliminar').action        = `/huespedes/${encodeURIComponent(btn.dataset.numDoc)}`;
    });

    // Carga inicial: restaura la página guardada en sesión
    const paginaInicial = window.paginaRetorno ?? 1;
    window.buscarHuespedes(paginaInicial);
});
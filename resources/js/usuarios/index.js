// ─── OJITO CONTRASEÑA ───────────────────────────────────────
//  Alterna visibilidad del campo password y cambia el ícono.
// ────────────────────────────────────────────────────────────
window.togglePassword = function (inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
};


// ─── VERIFICAR CONTRASEÑA ───────────────────────────────────────────────────────────────────────────────────
//  Valida longitud mínima en tiempo real (oninput).
//  Muestra/oculta mensaje de error y activa clase .error en el contenedor .campo-password para el borde rojo.
// ────────────────────────────────────────────────────────────────────────────────────────────────────────────
window.verificarPassword = function (input, errorId, requerida = true) {
    const errorEl    = document.getElementById(errorId);
    const campoPwd   = input.closest('.campo-password');
    const valor      = input.value;

    if (!valor) {
        errorEl.textContent = requerida ? 'La contraseña es obligatoria.' : '';
        campoPwd.classList.toggle('error', requerida);
        return;
    }

    if (valor.length < 6) {
        errorEl.textContent = 'La contraseña debe tener al menos 6 caracteres.';
        campoPwd.classList.add('error');
    } else {
        errorEl.textContent = '';
        campoPwd.classList.remove('error');
    }
};


// ─── VERIFICAR EMAIL (AJAX) ─────────────────────────────────
//  Consulta al servidor si el correo ya existe (onblur).
//  Activa clase .error en .campo-input si hay duplicado.
// ────────────────────────────────────────────────────────────
window.verificarEmail = function (input, errorId, usuarioId = 0) {
    const email      = input.value;
    const errorEl    = document.getElementById(errorId);
    const campoInput = input.closest('.campo-input');

    if (!email) {
        errorEl.textContent = '';
        return;
    }

    fetch(`/usuarios/verificar-email?email=${encodeURIComponent(email)}&id=${usuarioId}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(data => {
            if (data.existe) {
                errorEl.textContent = 'Este correo electrónico ya está registrado.';
                campoInput.classList.add('error');
            } else {
                errorEl.textContent = '';
                campoInput.classList.remove('error');
            }
        });
};


// ─── VALIDAR FORMULARIO ─────────────────────────────────────
//  Validación final antes del submit (onsubmit).
//  Bloquea el envío si hay errores visibles o campos vacíos.
//  Retorna true para permitir el submit, false para bloquearlo.
// ────────────────────────────────────────────────────────────
window.validarFormulario = function (formId, errorEmailId, errorPasswordId) {
    const errorEmail    = document.getElementById(errorEmailId);
    const errorPassword = document.getElementById(errorPasswordId);

    // Bloquear si ya hay mensajes de error activos
    if (errorEmail.textContent.trim() !== '')    return false;
    if (errorPassword.textContent.trim() !== '') return false;

    // Validación específica al crear (contraseña obligatoria)
    if (formId === 'formCrear') {
        const password = document.getElementById('crearPassword').value;
        if (!password) {
            errorPassword.textContent = 'La contraseña es obligatoria.';
            return false;
        }
        if (password.length < 6) {
            errorPassword.textContent = 'La contraseña debe tener al menos 6 caracteres.';
            return false;
        }
    }

    // Validación específica al editar (contraseña opcional, pero si se ingresa debe cumplir)
    if (formId === 'formEditar') {
        const password = document.getElementById('editPassword').value;
        if (password && password.length < 6) {
            errorPassword.textContent = 'La contraseña debe tener al menos 6 caracteres.';
            return false;
        }
    }

    return true;
};


// ─── MODALES ────────────────────────────────────────────────
//  Listeners de Bootstrap para abrir, cerrar y limpiar modales.
// ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // ── Modal Crear: limpiar campos y errores al cerrar ──
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        document.getElementById('errorEmailCrear').textContent    = '';
        document.getElementById('errorPasswordCrear').textContent = '';
        document.getElementById('crearEmail').closest('.campo-input').classList.remove('error');
        document.querySelector('#modalCrear .campo-password').classList.remove('error');
    });

    // ── Modal Editar: cargar datos del usuario al abrir ──
    document.getElementById('modalEditar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editNombre').value    = btn.dataset.name;
        document.getElementById('editEmail').value     = btn.dataset.email;
        document.getElementById('editRol').value       = btn.dataset.rol;
        document.getElementById('editActivo').value    = btn.dataset.activo;
        document.getElementById('editPassword').value  = '';
        document.getElementById('editUsuarioId').value = btn.dataset.id;
        document.getElementById('formEditar').action   = '/usuarios/' + btn.dataset.id;
    });

    // ── Modal Editar: limpiar campos y errores al cerrar ──
    document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditar').reset();
        document.getElementById('errorEmailEditar').textContent    = '';
        document.getElementById('errorPasswordEditar').textContent = '';
        document.getElementById('editEmail').closest('.campo-input').classList.remove('error');
        document.querySelector('#modalEditar .campo-password').classList.remove('error');
    });

    // ── Modal Eliminar: cargar nombre y acción del formulario al abrir ──
    document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarNombre').textContent = btn.dataset.name;
        document.getElementById('formEliminar').action        = '/usuarios/' + btn.dataset.id;
    });

});
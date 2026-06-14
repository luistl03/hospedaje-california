// ─── OJITO CONTRASEÑA ───
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

// ─── VERIFICAR CONTRASEÑA ───
window.verificarPassword = function (input, errorId, requerida = true) {
    const errorEl = document.getElementById(errorId);
    const valor   = input.value;

    if (!valor) {
        errorEl.textContent = requerida ? 'La contraseña es obligatoria.' : '';
        input.closest('.campo-password').style.borderBottomColor = requerida ? '#cc0000' : '';
        return;
    }

    if (valor.length < 6) {
        errorEl.textContent = 'La contraseña debe tener al menos 6 caracteres.';
        input.closest('.campo-password').style.borderBottomColor = '#cc0000';
    } else {
        errorEl.textContent = '';
        input.closest('.campo-password').style.borderBottomColor = '';
    }
};

// ─── VERIFICAR EMAIL (AJAX) ───
window.verificarEmail = function (input, errorId, usuarioId = 0) {
    const email   = input.value;
    const errorEl = document.getElementById(errorId);

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
                input.closest('.campo-input').style.borderBottomColor = '#cc0000';
            } else {
                errorEl.textContent = '';
                input.closest('.campo-input').style.borderBottomColor = '';
            }
        });
};

// ─── VALIDAR FORMULARIO ───
window.validarFormulario = function (formId, errorEmailId, errorPasswordId) {
    const errorEmail    = document.getElementById(errorEmailId);
    const errorPassword = document.getElementById(errorPasswordId);

    if (errorEmail.textContent.trim() !== '')    return false;
    if (errorPassword.textContent.trim() !== '') return false;

    if (formId === 'formCrear') {
        const password = document.getElementById('crearPassword').value;
        if (!password) {
            document.getElementById(errorPasswordId).textContent = 'La contraseña es obligatoria.';
            return false;
        }
        if (password.length < 6) {
            document.getElementById(errorPasswordId).textContent = 'La contraseña debe tener al menos 6 caracteres.';
            return false;
        }
    }

    if (formId === 'formEditar') {
        const password = document.getElementById('editPassword').value;
        if (password && password.length < 6) {
            document.getElementById(errorPasswordId).textContent = 'La contraseña debe tener al menos 6 caracteres.';
            return false;
        }
    }

    return true;
};

// ─── MODALES ───
document.addEventListener('DOMContentLoaded', () => {

    // Modal crear — limpiar al cerrar
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        document.getElementById('errorEmailCrear').textContent    = '';
        document.getElementById('errorPasswordCrear').textContent = '';
        document.getElementById('crearEmail').closest('.campo-input').style.borderBottomColor    = '';
        document.querySelector('#modalCrear .campo-password').style.borderBottomColor = '';
    });

    // Modal editar — cargar datos
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

    // Modal editar — limpiar al cerrar
    document.getElementById('modalEditar').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formEditar').reset();
        document.getElementById('errorEmailEditar').textContent    = '';
        document.getElementById('errorPasswordEditar').textContent = '';
        document.getElementById('editEmail').closest('.campo-input').style.borderBottomColor    = '';
        document.querySelector('#modalEditar .campo-password').style.borderBottomColor = '';
    });

    // Modal eliminar — cargar datos
    document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('eliminarNombre').textContent = btn.dataset.name;
        document.getElementById('formEliminar').action        = '/usuarios/' + btn.dataset.id;
    });
});
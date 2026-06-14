import { Modal } from 'bootstrap';

// ─── CONFIGURACIÓN HORARIA ───
const CHECKIN_NORMAL = 13 * 60;
const EARLY_INICIO   = 6 * 60 + 1;
const EARLY_FIN      = 11 * 60;
const MADRUGADA_FIN  = 6 * 60;
const HORAS_MINIMAS  = 2;

const hoy     = new Date();
const HOY_STR = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;

let franjaDetectada = '';
let pasoActual      = 1;

// ─── UTILIDADES ───
function toLocalDateTimeString(date) {
    const offset = date.getTimezoneOffset() * 60000;
    const local  = new Date(date.getTime() - offset);
    return local.toISOString().slice(0, 16);
}

function tipoEstadiaNombre() {
    const select = document.getElementById('tipoEstadiaId');
    const option = select.options[select.selectedIndex];
    return option ? option.dataset.nombre : '';
}

// ─── ALERTA FEEDBACK ───
function mostrarAlerta(tipo, mensaje) {
    const clases = {
        exito: 'alerta-exito',
        error: 'login-error',
    };
    const iconos = {
        exito: 'bi-check-circle',
        error: 'bi-exclamation-circle',
    };

    // Eliminar alerta previa si existe
    document.querySelectorAll('.alerta-exito, .login-error').forEach(a => a.remove());

    const div = document.createElement('div');
    div.className = `${clases[tipo]} mb-3`;
    div.innerHTML = `<i class="bi ${iconos[tipo]}"></i> ${mensaje}`;

    // Insertar antes de la barra de filtros
    const filtros = document.querySelector('.filtros-barra');
    filtros.parentNode.insertBefore(div, filtros);

    // Auto-ocultar a los 4 segundos
    setTimeout(() => div.remove(), 4000);
}

// ─── PASO 1: LÓGICA HORARIA ───
window.actualizarPaso1 = function () {
    const fechaEntradaInput = document.getElementById('fechaEntrada');
    const fechaSalidaInput  = document.getElementById('fechaSalida');

    fechaEntradaInput.value = '';
    fechaSalidaInput.value  = '';
    fechaSalidaInput.min    = '';

    // Ocultar aviso y limpiar clases de franja
    ocultarAvisoFranja();
    franjaDetectada = '';

    const ahora = new Date();
    ahora.setSeconds(0, 0);
    fechaEntradaInput.min  = toLocalDateTimeString(ahora);
    fechaEntradaInput.step = 600;
    fechaSalidaInput.step  = 3600;
};

window.validarEntrada = function () {
    const fechaEntradaInput = document.getElementById('fechaEntrada');
    const fechaSalidaInput  = document.getElementById('fechaSalida');
    const tipo              = tipoEstadiaNombre();

    const ahora = new Date();
    ahora.setSeconds(0, 0);

    if (fechaEntradaInput.value) {
        const entradaSel = new Date(fechaEntradaInput.value);
        if (entradaSel < ahora) {
            fechaEntradaInput.value = toLocalDateTimeString(ahora);
        }
    }

    if (!tipo || !fechaEntradaInput.value) return;

    // Redondear minutos al siguiente múltiplo de 10
    const entrada = new Date(fechaEntradaInput.value);
    const mins    = entrada.getMinutes();
    if (mins % 10 !== 0) {
        const redondeado = Math.ceil(mins / 10) * 10;
        if (redondeado === 60) {
            entrada.setHours(entrada.getHours() + 1, 0, 0, 0);
        } else {
            entrada.setMinutes(redondeado, 0, 0);
        }
        fechaEntradaInput.value = toLocalDateTimeString(entrada);
    }

    if (tipo === 'horas') {
        const salidaMin = new Date(entrada.getTime() + HORAS_MINIMAS * 60 * 60 * 1000);
        fechaSalidaInput.min   = toLocalDateTimeString(salidaMin);
        fechaSalidaInput.value = toLocalDateTimeString(salidaMin);
        mostrarAvisoFranja('horas');

    } else if (tipo === 'noches') {
        const horaMin   = entrada.getHours() * 60 + entrada.getMinutes();
        franjaDetectada = detectarFranja(horaMin);
        const salidaBase = calcularCheckout(entrada, franjaDetectada);
        fechaSalidaInput.min   = toLocalDateTimeString(salidaBase);
        fechaSalidaInput.value = toLocalDateTimeString(salidaBase);
        mostrarAvisoFranja(franjaDetectada);
    }
};

window.validarSalida = function () {
    const fechaEntradaInput = document.getElementById('fechaEntrada');
    const fechaSalidaInput  = document.getElementById('fechaSalida');
    const tipo              = tipoEstadiaNombre();

    if (!tipo || !fechaEntradaInput.value || !fechaSalidaInput.value) return;

    const entrada = new Date(fechaEntradaInput.value);

    if (tipo === 'horas') {
        const salidaMin = new Date(entrada.getTime() + HORAS_MINIMAS * 60 * 60 * 1000);
        const salidaSel = new Date(fechaSalidaInput.value);
        if (salidaSel < salidaMin) {
            fechaSalidaInput.value = toLocalDateTimeString(salidaMin);
        }
        mostrarAvisoFranja('horas');

    } else if (tipo === 'noches') {
        const salidaBase = calcularCheckout(entrada, franjaDetectada);
        const salidaSel  = new Date(fechaSalidaInput.value);
        if (salidaSel < salidaBase) {
            fechaSalidaInput.value = toLocalDateTimeString(salidaBase);
        } else {
            salidaSel.setHours(11, 0, 0, 0);
            fechaSalidaInput.value = toLocalDateTimeString(salidaSel);
        }
        mostrarAvisoFranja(franjaDetectada);
    }
};

function detectarFranja(horaEnMinutos) {
    if (horaEnMinutos >= 0 && horaEnMinutos <= MADRUGADA_FIN)          return 'madrugada';
    if (horaEnMinutos >= EARLY_INICIO && horaEnMinutos <= EARLY_FIN)   return 'early';
    if (horaEnMinutos > EARLY_FIN && horaEnMinutos < CHECKIN_NORMAL)   return 'intermedio';
    return 'normal';
}

function calcularCheckout(entrada, franja) {
    const salida = new Date(entrada);
    if (franja === 'madrugada') {
        salida.setHours(11, 0, 0, 0);
    } else {
        salida.setDate(salida.getDate() + 1);
        salida.setHours(11, 0, 0, 0);
    }
    return salida;
}

// ─── AVISO FRANJA: usa classList, no style.* ───
const FRANJAS_CLASES = ['franja-horas', 'franja-madrugada', 'franja-early', 'franja-intermedio', 'franja-normal'];

function ocultarAvisoFranja() {
    const aviso = document.getElementById('avisoFranja');
    aviso.style.display = 'none';
    aviso.classList.remove(...FRANJAS_CLASES);
}

function mostrarAvisoFranja(franja) {
    const aviso        = document.getElementById('avisoFranja');
    const texto        = document.getElementById('textoFranja');
    const fechaEntrada = document.getElementById('fechaEntrada').value;
    const fechaSalida  = document.getElementById('fechaSalida').value;
    const tipo         = tipoEstadiaNombre();

    // ── HORAS ──
    if (tipo === 'horas') {
        if (!fechaEntrada || !fechaSalida) { ocultarAvisoFranja(); return; }
        const horas      = Math.round((new Date(fechaSalida) - new Date(fechaEntrada)) / 3600000);
        const horasTexto = horas === 1 ? '1 hora' : `${horas} horas`;
        texto.textContent = `Estadía por horas — Se cobrarán ${horasTexto}.`;
        aviso.classList.remove(...FRANJAS_CLASES);
        aviso.classList.add('franja-horas');
        aviso.style.display = 'block';
        return;
    }

    // ── NOCHES ──
    let noches = 1;
    if (fechaEntrada && fechaSalida) {
        const entrada    = new Date(fechaEntrada);
        const salida     = new Date(fechaSalida);
        const entradaDia = new Date(entrada.getFullYear(), entrada.getMonth(), entrada.getDate());
        const salidaDia  = new Date(salida.getFullYear(), salida.getMonth(), salida.getDate());
        const diffDias   = Math.round((salidaDia - entradaDia) / 86400000);
        noches = franjaDetectada === 'madrugada'
            ? (diffDias === 0 ? 1 : diffDias + 1)
            : (diffDias < 1 ? 1 : diffDias);
    }

    const nochesTexto = noches === 1 ? '1 noche' : `${noches} noches`;
    const mensajes = {
        madrugada:  `Ingreso en madrugada — Se cobra ${nochesTexto}. Check out: 11:00 AM.`,
        early:      `Ingreso temprano — Se cobra ${nochesTexto} + recargo 2 horas. Check out: 11:00 AM.`,
        intermedio: `Ingreso intermedio — Se cobra ${nochesTexto} sin recargo. Check out: 11:00 AM.`,
        normal:     `Se cobra ${nochesTexto}. Check out: 11:00 AM.`,
    };

    if (!mensajes[franja]) { ocultarAvisoFranja(); return; }

    texto.textContent = mensajes[franja];
    aviso.classList.remove(...FRANJAS_CLASES);
    aviso.classList.add(`franja-${franja}`);
    aviso.style.display = 'block';
}

// ─── CAMBIAR PASO ───
window.cambiarPaso = function (direccion) {
    if (direccion === 1 && pasoActual === 1 && !validarPaso1()) return;
    if (direccion === 1 && pasoActual === 2 && !validarPaso2()) return;
    if (direccion === 1 && pasoActual === 3 && !validarPaso3()) return;

    document.getElementById(`paso${pasoActual}`).style.display = 'none';
    document.getElementById(`indicador${pasoActual}`).classList.remove('paso-activo');

    pasoActual += direccion;

    document.getElementById(`paso${pasoActual}`).style.display = 'block';
    document.getElementById(`indicador${pasoActual}`).classList.add('paso-activo');

    document.getElementById('btnAnterior').style.display = pasoActual === 1 ? 'none' : 'inline-flex';

    if (pasoActual === 2) cargarHabitacionesDisponiblesIfNeeded();

    if (pasoActual === 4) {
        window.inicializarPaso4();
        document.getElementById('btnSiguiente').style.display = 'none';
        document.getElementById('btnConfirmar').style.display = 'inline-flex';
    } else {
        document.getElementById('btnSiguiente').style.display = 'inline-flex';
        document.getElementById('btnConfirmar').style.display = 'none';
    }
};

function validarPaso1() {
    const tipo    = document.getElementById('tipoEstadiaId').value;
    const entrada = document.getElementById('fechaEntrada').value;
    const salida  = document.getElementById('fechaSalida').value;
    if (!tipo || !entrada || !salida) {
        alert('Complete todos los campos obligatorios.');
        return false;
    }
    return true;
}

function validarPaso2() {
    if (habitacionesSeleccionadas.length === 0) {
        alert('Seleccione al menos una habitación.');
        return false;
    }
    return true;
}

function validarPaso3() {
    if (huespedесSeleccionados.length === 0) {
        alert('Agregue al menos un huésped.');
        return false;
    }
    return true;
}

// ─── FILTROS ───
let paginaActual = 1;

window.buscarReservas = function (pagina = 1, esInicial = false) {
    paginaActual = pagina;

    const params = new URLSearchParams({
        estado_id:     document.getElementById('filtroEstado').value,
        fecha_entrada: document.getElementById('filtroFechaEntrada').value,
        huesped:       document.getElementById('filtroHuesped')?.value ?? '',
        habitacion:    document.getElementById('filtroHabitacion')?.value ?? '',
        inicial:       esInicial ? '1' : '',
        pagina,
    });

    fetch(`/reservas/filtrar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
    .then(res => res.json())
    .then(resp => {
        const tbody = document.querySelector('#tablaReservas tbody');
        tbody.innerHTML = '';

        if (resp.data.length === 0) {
            const msg = esInicial
                ? 'No hay actividad registrada para hoy.'
                : 'No se encontraron reservas con los filtros aplicados.';
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center; padding:40px 20px; color:var(--gris-texto);">
                        <i class="bi bi-search" style="font-size:1.5rem; display:block; margin-bottom:8px; opacity:0.4;"></i>
                        ${msg}
                    </td>
                </tr>`;
            document.getElementById('paginacion').style.display = 'none';
            return;
        }

        const estadoBadge = {
            pendiente:  'badge-estado-reservada',
            activa:     'badge-estado-disponible',
            finalizada: 'badge-estado-limpieza',
            cancelada:  'badge-estado-mantenimiento',
        };

        resp.data.forEach(r => {
            const acciones = construirAcciones(r);
            tbody.innerHTML += `
                <tr>
                    <td><strong>#${r.id}</strong></td>
                    <td>
                        <span class="huesped-principal">${r.huesped_principal}</span>
                        ${r.huespedes_extra > 0
                            ? `<span class="badge-extra-huespedes">+${r.huespedes_extra}</span>`
                            : ''}
                    </td>
                    <td>${r.habitaciones}</td>
                    <td>${r.tipo_estadia}</td>
                    <td>${r.fecha_entrada}</td>
                    <td>${r.fecha_salida}</td>
                    <td>
                        <span class="badge-estado ${estadoBadge[r.estado_nombre]}">
                            ${r.estado_nombre.charAt(0).toUpperCase() + r.estado_nombre.slice(1)}
                        </span>
                    </td>
                    <td class="acciones">${acciones}</td>
                </tr>`;
        });

        renderizarPaginacion(resp.pagina_actual, resp.total_paginas);
    });
};

window.limpiarFiltros = function () {
    document.getElementById('filtroEstado').value       = '';
    document.getElementById('filtroFechaEntrada').value = HOY_STR;
    document.getElementById('filtroHuesped').value      = '';
    document.getElementById('filtroHabitacion').value   = '';
    window.buscarReservas(1);
};

// ─── PAGINACIÓN ───
function renderizarPaginacion(actual, total) {
    const contenedor = document.getElementById('paginacion');

    if (total <= 1) { contenedor.style.display = 'none'; return; }

    contenedor.style.display = 'flex';
    contenedor.innerHTML     = '';

    const btn = (i, label = null, disabled = false, activo = false) => `
        <button class="btn-pagina ${activo ? 'btn-pagina-activo' : ''} ${disabled ? 'btn-pagina-disabled' : ''}"
            onclick="window.buscarReservas(${i})" ${disabled ? 'disabled' : ''}>
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

// ─── PASO 2: HABITACIONES ───
let habitacionesSeleccionadas = [];
let habitacionesData          = {};
let habitacionesCargadasPara = '';

function cargarHabitacionesDisponiblesIfNeeded() {
    const entrada       = document.getElementById('fechaEntrada').value;
    const salida        = document.getElementById('fechaSalida').value;
    const tipoEstadiaId = document.getElementById('tipoEstadiaId').value;

    // Firma única de los parámetros actuales
    const firma = `${entrada}|${salida}|${tipoEstadiaId}`;

    // Si ya se cargó con estos mismos parámetros, no recargar
    if (firma === habitacionesCargadasPara) return;

    // Parámetros cambiaron — resetear selección y recargar
    habitacionesCargadasPara  = firma;
    habitacionesSeleccionadas = [];
    habitacionesData          = {};

    cargarHabitacionesDisponibles();
}

function cargarHabitacionesDisponibles() {
    document.getElementById('paso2Cargando').style.display = 'block';
    document.getElementById('paso2Mapa').style.display     = 'none';
    document.getElementById('paso2Vacio').style.display    = 'none';
    document.getElementById('paso2Resumen').style.display  = 'none';

    const params = new URLSearchParams({
        fecha_entrada:   document.getElementById('fechaEntrada').value,
        fecha_salida:    document.getElementById('fechaSalida').value,
        tipo_estadia_id: document.getElementById('tipoEstadiaId').value,
    });

    fetch(`/reservas/habitaciones-disponibles?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(resp => {
            document.getElementById('paso2Cargando').style.display = 'none';

            if (!resp.pisos || resp.pisos.length === 0) {
                document.getElementById('paso2Vacio').style.display = 'block';
                return;
            }

            resp.pisos.forEach(p => {
                p.habitaciones.forEach(h => {
                    habitacionesData[h.numero] = h;
                });
            });

            renderizarMapa(resp.pisos, resp.tipo_nombre);
            document.getElementById('paso2Mapa').style.display = 'block';
        });
}

function renderizarMapa(pisos, tipoNombre) {
    const mapa = document.getElementById('paso2Mapa');
    mapa.innerHTML = '';

    pisos.forEach(p => {
        const seccion = document.createElement('div');
        seccion.style.marginBottom = '20px';

        seccion.innerHTML = `
            <div class="piso-label">Piso ${p.piso}</div>
            <div class="piso-habitaciones">
                ${p.habitaciones.map(h => `
                    <div class="tarjeta-habitacion" id="tarjeta-${h.numero}"
                        onclick="window.toggleHabitacion(${h.numero})">
                        <div class="tarjeta-numero">N° ${h.numero}</div>
                        <div class="tarjeta-tipo">${h.tipo_nombre}</div>
                        <div class="tarjeta-precio">
                            ${tipoNombre === 'horas'
                                ? `S/ ${h.precio_hora} / hora`
                                : `S/ ${h.precio_noche} / noche`}
                        </div>
                    </div>
                `).join('')}
            </div>`;

        mapa.appendChild(seccion);
    });
}

// ─── TOGGLE HABITACIÓN: usa classList, no style.* ───
window.toggleHabitacion = function (numero) {
    const tarjeta = document.getElementById(`tarjeta-${numero}`);
    const idx     = habitacionesSeleccionadas.indexOf(numero);

    if (idx === -1) {
        habitacionesSeleccionadas.push(numero);
        tarjeta.classList.add('tarjeta-seleccionada');
    } else {
        habitacionesSeleccionadas.splice(idx, 1);
        tarjeta.classList.remove('tarjeta-seleccionada');
    }

    actualizarResumenPaso2();
    maxHuespedesPermitido = habitacionesSeleccionadas.reduce(
        (sum, n) => sum + (habitacionesData[n]?.max_huespedes ?? 1), 0
    );
};

function actualizarResumenPaso2() {
    const resumen = document.getElementById('paso2Resumen');

    if (habitacionesSeleccionadas.length === 0) {
        resumen.style.display = 'none';
        return;
    }

    const tipo    = tipoEstadiaNombre();
    const entrada = new Date(document.getElementById('fechaEntrada').value);
    const salida  = new Date(document.getElementById('fechaSalida').value);
    let   total   = 0;
    let   labelUnidad = '';

    if (tipo === 'horas') {
        const unidades  = Math.round((salida - entrada) / 3600000);
        labelUnidad     = unidades === 1 ? '1 hora' : `${unidades} horas`;
        habitacionesSeleccionadas.forEach(n => {
            total += habitacionesData[n].precio_hora_raw * unidades;
        });

    } else {
        const entradaDia = new Date(entrada.getFullYear(), entrada.getMonth(), entrada.getDate());
        const salidaDia  = new Date(salida.getFullYear(), salida.getMonth(), salida.getDate());
        const diffDias   = Math.round((salidaDia - entradaDia) / 86400000);
        const unidades   = franjaDetectada === 'madrugada'
            ? (diffDias === 0 ? 1 : diffDias + 1)
            : (diffDias < 1 ? 1 : diffDias);
        labelUnidad = unidades === 1 ? '1 noche' : `${unidades} noches`;
        
        labelUnidad = unidades === 1 ? '1 noche' : `${unidades} noches`;
        if (franjaDetectada === 'early') {
            labelUnidad += ' + recargo ingreso temprano';
        }

        habitacionesSeleccionadas.forEach(n => {
            let subtotal = habitacionesData[n].precio_noche_raw * unidades;
            if (franjaDetectada === 'early') {
                subtotal += habitacionesData[n].precio_hora_raw * 2;
            }
            total += subtotal;
        });
    }

    const habs = habitacionesSeleccionadas.map(n => `N°${n}`).join(', ');
    document.getElementById('paso2ResumenTexto').textContent = `${habs} · ${labelUnidad}`;
    document.getElementById('paso2ResumenTotal').textContent = `Total: S/ ${total.toFixed(2)}`;
    resumen.style.display = 'block';
}

// ─── PASO 3: HUÉSPEDES ───
let huespedесSeleccionados = []; // [{id, nombre, tipo_doc, num_doc, telefono}]
let maxHuespedesPermitido = 0; 

window.buscarHuesped = function () {
    const tipoDoc = document.getElementById('paso3TipoDoc').value;
    const numDoc  = document.getElementById('paso3NumDoc').value.trim();
    const nombre  = document.getElementById('paso3Nombre').value.trim();

    const params = new URLSearchParams();
    if (tipoDoc && numDoc) {
        params.set('tipo_doc_id', tipoDoc);
        params.set('num_doc', numDoc);
    } else if (nombre) {
        params.set('nombre', nombre);
    } else {
        alert('Ingrese tipo + número de documento, o un nombre para buscar.');
        return;
    }

    fetch(`/huespedes/buscar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(resp => {
            const listaEl  = document.getElementById('paso3ListaResultados');
            const resEl    = document.getElementById('paso3Resultados');
            const vacioEl  = document.getElementById('paso3Vacio');

            listaEl.innerHTML = '';

            if (!resp.data || resp.data.length === 0) {
                resEl.style.display   = 'none';
                vacioEl.style.display = 'block';
                return;
            }

            vacioEl.style.display = 'none';
            resEl.style.display   = 'block';

            resp.data.forEach(h => {
                const yaEsta         = huespedесSeleccionados.some(s => s.id === h.id);
                const limiteAlcanzado = huespedесSeleccionados.length >= maxHuespedesPermitido && maxHuespedesPermitido > 0;
                const deshabilitado  = yaEsta || (limiteAlcanzado && !yaEsta);

                const item = document.createElement('div');
                item.className = 'paso3-item';
                item.innerHTML = `
                    <span class="huesped-nombre">${h.nombre}</span>
                    <span class="huesped-doc">${h.tipo_doc.toUpperCase()}</span>
                    <span class="huesped-doc">${h.num_doc}</span>
                    <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                        ${h.telefono !== '—' ? h.telefono : '—'}
                    </span>
                    <button type="button"
                        class="btn-agregar-huesped ${yaEsta ? 'btn-agregar-ya' : ''} ${limiteAlcanzado && !yaEsta ? 'btn-agregar-limite' : ''}"
                        onclick="window.agregarHuesped(${h.id},'${escaparTexto(h.nombre)}','${escaparTexto(h.tipo_doc)}','${escaparTexto(h.num_doc)}','${escaparTexto(h.telefono)}')"
                        ${deshabilitado ? 'disabled' : ''}>
                        <i class="bi bi-${yaEsta ? 'check-lg' : 'person-plus'}"></i>
                        ${yaEsta ? 'Agregado' : 'Agregar'}
                    </button>`;
                listaEl.appendChild(item);
            });
        });
};

function escaparTexto(str) {
    return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

window.agregarHuesped = function (id, nombre, tipoDoc, numDoc, telefono) {
    if (huespedесSeleccionados.some(h => h.id === id)) return;
    if (maxHuespedesPermitido > 0 && huespedесSeleccionados.length >= maxHuespedesPermitido) return;

    huespedесSeleccionados.push({ id, nombre, tipoDoc, numDoc, telefono });
    renderizarSeleccionados();
    actualizarContadorHuespedes();

    // Deshabilitar el botón en resultados
    const lista = document.getElementById('paso3ListaResultados');
    lista.querySelectorAll('button').forEach(btn => {
        if (btn.onclick && btn.onclick.toString().includes(`agregarHuesped(${id},`)) {
            btn.disabled   = true;
            btn.classList.add('btn-agregar-ya');
            btn.innerHTML  = '<i class="bi bi-check-lg"></i> Agregado';
        }
    });
};

window.quitarHuesped = function (id) {
    huespedесSeleccionados = huespedесSeleccionados.filter(h => h.id !== id);
    renderizarSeleccionados();
    actualizarContadorHuespedes();

    // Rehabilitar en resultados si está visible
    const lista = document.getElementById('paso3ListaResultados');
    lista.querySelectorAll('button').forEach(btn => {
        const match = btn.getAttribute('onclick')?.match(/agregarHuesped\((\d+),/);
        if (match && parseInt(match[1]) === id) {
            btn.disabled   = false;
            btn.classList.remove('btn-agregar-ya');
            btn.innerHTML  = '<i class="bi bi-plus-lg"></i> Agregar';
        }
    });
};

window.limpiarBuscadorHuesped = function () {
    document.getElementById('paso3TipoDoc').value        = '';
    document.getElementById('paso3NumDoc').value         = '';
    document.getElementById('paso3Nombre').value         = '';
    document.getElementById('paso3ListaResultados').innerHTML = '';
    document.getElementById('paso3Resultados').style.display  = 'none';
    document.getElementById('paso3Vacio').style.display       = 'none';
};

// ─── ACTUALIZAR CONTADOR Y AVISO DE LÍMITE ───
function actualizarContadorHuespedes() {
    const total  = huespedесSeleccionados.length;
    const limite = maxHuespedesPermitido;
    const badge  = document.getElementById('paso3ContadorBadge');
    const aviso  = document.getElementById('paso3AvisoLimite');

    if (badge) {
        badge.textContent     = limite > 0 ? `${total} / ${limite}` : total;
        badge.style.background = (limite > 0 && total >= limite)
            ? 'linear-gradient(135deg, #dc3545, #bb2d3b)'
            : 'linear-gradient(135deg, var(--dorado), var(--dorado-oscuro))';
    }

    if (aviso) {
        if (limite > 0 && total >= limite) {
            aviso.textContent   = `Límite alcanzado: máximo ${limite} huésped${limite !== 1 ? 'es' : ''} para las habitaciones seleccionadas.`;
            aviso.style.display = 'block';
        } else {
            aviso.style.display = 'none';
        }
    }
}

function renderizarSeleccionados() {
    const contenedor = document.getElementById('paso3ListaSeleccionados');
    const seccion    = document.getElementById('paso3Seleccionados');
    const badge      = document.getElementById('paso3ContadorBadge');

    contenedor.innerHTML = '';

    if (huespedесSeleccionados.length === 0) {
        seccion.style.display = 'none';
        return;
    }

    actualizarContadorHuespedes();
    seccion.style.display = 'block';

    huespedесSeleccionados.forEach(h => {
        const item = document.createElement('div');
        item.className = 'paso3-seleccionado-item';
        item.innerHTML = `
            <span class="huesped-nombre">${h.nombre}</span>
            <span class="huesped-doc">${h.tipoDoc.toUpperCase()}</span>
            <span class="huesped-doc">${h.numDoc}</span>
            <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                ${h.telefono !== '—' ? h.telefono : '—'}
            </span>
            <button type="button" class="btn-quitar-huesped"
                onclick="window.quitarHuesped(${h.id})" title="Quitar">
                <i class="bi bi-x-lg"></i>
            </button>`;
        contenedor.appendChild(item);
    });
}

// ─── PASO 4: PAGOS ───
let paso4MontoTotal  = 0;
let paso4MontoMinimo = 0;
let paso4EsInmediata = false;

window.inicializarPaso4 = function () {
    const entrada    = new Date(document.getElementById('fechaEntrada').value);
    const ahora      = new Date();
    const diffMinutos = (entrada - ahora) / 60000;
    paso4EsInmediata = diffMinutos <= 10;

    const tipo      = tipoEstadiaNombre();
    const fechaEnt  = new Date(document.getElementById('fechaEntrada').value);
    const fechaSal  = new Date(document.getElementById('fechaSalida').value);

    let montoBase  = 0;
    let montoEarly = 0;
    const filas    = [];

    habitacionesSeleccionadas.forEach(numero => {
        const h = habitacionesData[numero];

        if (tipo === 'horas') {
            const horas    = Math.round((fechaSal - fechaEnt) / 3600000);
            const subtotal = h.precio_hora_raw * horas;
            montoBase += subtotal;
            filas.push({
                label: `N°${numero} · ${horas === 1 ? '1 hora' : horas + ' horas'}`,
                monto: subtotal,
                clase: '',
            });
        } else {
            const entDia   = new Date(fechaEnt.getFullYear(), fechaEnt.getMonth(), fechaEnt.getDate());
            const salDia   = new Date(fechaSal.getFullYear(), fechaSal.getMonth(), fechaSal.getDate());
            const diffDias = Math.round((salDia - entDia) / 86400000);
            const noches   = franjaDetectada === 'madrugada'
                ? (diffDias === 0 ? 1 : diffDias + 1)
                : (diffDias < 1 ? 1 : diffDias);

            const subtotal = h.precio_noche_raw * noches;
            montoBase += subtotal;
            filas.push({
                label: `N°${numero} · ${noches === 1 ? '1 noche' : noches + ' noches'}`,
                monto: subtotal,
                clase: '',
            });

            if (franjaDetectada === 'early') {
                const recargo = h.precio_hora_raw * 2;
                montoEarly   += recargo;
                filas.push({
                    label: `N°${numero} · Recargo ingreso temprano (2 h)`,
                    monto: recargo,
                    clase: 'paso4-fila-early',
                });
            }
        }
    });

    paso4MontoTotal  = montoBase + montoEarly;
    paso4MontoMinimo = paso4EsInmediata
        ? paso4MontoTotal
        : Math.ceil(paso4MontoTotal * 0.5 * 100) / 100;

    // Renderizar filas
    const contenedor = document.getElementById('paso4FilasDesglose');
    contenedor.innerHTML = '';
    filas.forEach(f => {
        contenedor.innerHTML += `
            <div class="paso4-fila">
                <span class="paso4-fila-label ${f.clase}">${f.label}</span>
                <span class="${f.clase}">S/ ${f.monto.toFixed(2)}</span>
            </div>`;
    });

    document.getElementById('paso4Total').textContent = `S/ ${paso4MontoTotal.toFixed(2)}`;

    // Mínimo label
    document.getElementById('paso4MinimoLabel').textContent = paso4EsInmediata
        ? '(pago completo requerido)'
        : `(mínimo 50%: S/ ${paso4MontoMinimo.toFixed(2)})`;

    // Aviso ocupación
    const aviso = document.getElementById('paso4AvisoOcupacion');
    if (paso4EsInmediata) {
        aviso.textContent = 'Ocupación inmediata — se requiere el pago total antes de ingresar.';
        aviso.className   = 'aviso-franja franja-early';
        aviso.style.display = 'block';
    } else {
        aviso.style.display = 'none';
    }

    // Setear mínimo en el input
    document.getElementById('paso4MontoPago').min   = paso4MontoMinimo;
    document.getElementById('paso4MontoPago').max   = paso4MontoTotal;
    document.getElementById('paso4MontoPago').value = paso4MontoMinimo.toFixed(2);

    document.getElementById('paso4ErrorMonto').style.display = 'none';
};

window.validarMontoPago = function () {
    const monto  = parseFloat(document.getElementById('paso4MontoPago').value) || 0;
    const errorEl = document.getElementById('paso4ErrorMonto');

    if (monto < paso4MontoMinimo) {
        errorEl.textContent  = `El mínimo es S/ ${paso4MontoMinimo.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else if (monto > paso4MontoTotal) {
        errorEl.textContent  = `No puede superar el total de S/ ${paso4MontoTotal.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }
};

function validarPaso4() {
    const monto   = parseFloat(document.getElementById('paso4MontoPago').value) || 0;
    const metodo  = document.getElementById('paso4MetodoId').value;

    if (!metodo) {
        alert('Seleccione un método de pago.');
        return false;
    }
    if (monto < paso4MontoMinimo) {
        alert(`El monto mínimo es S/ ${paso4MontoMinimo.toFixed(2)}`);
        return false;
    }
    if (monto > paso4MontoTotal) {
        alert(`El monto no puede superar S/ ${paso4MontoTotal.toFixed(2)}`);
        return false;
    }
    return true;
}

window.confirmarReserva = function () {
    if (!validarPaso4()) return;

    const btnConfirmar = document.getElementById('btnConfirmar');
    btnConfirmar.disabled  = true;
    btnConfirmar.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const payload = {
        fecha_entrada:   document.getElementById('fechaEntrada').value,
        fecha_salida:    document.getElementById('fechaSalida').value,
        tipo_estadia_id: document.getElementById('tipoEstadiaId').value,
        observacion:     document.querySelector('input[name="observacion"]').value,
        franja:          franjaDetectada || 'normal',
        habitaciones:    habitacionesSeleccionadas.map(numero => {
            const entrada = new Date(document.getElementById('fechaEntrada').value);
            const salida  = new Date(document.getElementById('fechaSalida').value);
            let unidades  = 0;
            if (tipoEstadiaNombre() === 'horas') {
                unidades = Math.round((salida - entrada) / 3600000);
            } else {
                const entDia   = new Date(entrada.getFullYear(), entrada.getMonth(), entrada.getDate());
                const salDia   = new Date(salida.getFullYear(), salida.getMonth(), salida.getDate());
                const diffDias = Math.round((salDia - entDia) / 86400000);
                unidades = franjaDetectada === 'madrugada'
                    ? (diffDias === 0 ? 1 : diffDias + 1)
                    : (diffDias < 1 ? 1 : diffDias);
            }
            return { numero, unidades };
        }),
        huespedes:  huespedесSeleccionados.map(h => h.id),
        monto_pago: parseFloat(document.getElementById('paso4MontoPago').value),
        metodo_id:  document.getElementById('paso4MetodoId').value,
    };

    fetch('/reservas', {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(payload),
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                alert(resp.error);
                btnConfirmar.disabled  = false;
                btnConfirmar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Reserva';
                return;
            }
            // ── Resetear botón ANTES de cerrar ──
            btnConfirmar.disabled  = false;
            btnConfirmar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Reserva';
            document.getElementById('modalCrear').querySelector('[data-bs-dismiss="modal"]').click();
            window.buscarReservas(1);
            mostrarAlerta('exito', 'Reserva creada correctamente.');
        })
        .catch(err => {
            console.error('FETCH ERROR:', err);
            alert('Ocurrió un error al guardar la reserva.');
            btnConfirmar.disabled  = false;
            btnConfirmar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Reserva';
        });
};

// ─── DROPDOWN DE ACCIONES POR RESERVA ───
function construirAcciones(r) {
    const items = [];

    items.push(`
        <button class="item-accion" onclick="window.verReserva(${r.id})">
            <i class="bi bi-eye"></i> Ver detalle
        </button>`);

    if (r.puede_checkin) {
        items.push(`
            <button class="item-accion" onclick="window.checkinReserva(${r.id})">
                <i class="bi bi-box-arrow-in-right"></i> Check-in
            </button>`);
    }

    if (r.puede_editar_fechas) {
        items.push(`
            <button class="item-accion" onclick="window.editarFechasReserva(${r.id})">
                <i class="bi bi-calendar-event"></i> Editar Fechas/Tipo
            </button>`);
    }

    if (r.puede_reasignar) {
        items.push(`
            <button class="item-accion" onclick="window.reasignarReserva(${r.id})">
                <i class="bi bi-arrow-left-right"></i> Reasignar Habitación
            </button>`);
    }

    if (r.puede_pago) {
        items.push(`
            <button class="item-accion" onclick="window.pagoReserva(${r.id})">
                <i class="bi bi-cash-coin"></i> Registrar pago
                <span class="item-accion-saldo">S/ ${r.saldo_pendiente.toFixed(2)}</span>
            </button>`);
    }

    if (r.puede_huespedes) {
        items.push(`
            <button class="item-accion" onclick="window.huespedесReserva(${r.id})">
                <i class="bi bi-people"></i> Editar huéspedes
            </button>`);
    }
    

    if (r.puede_extension) {
        items.push(`
            <button class="item-accion" onclick="window.extensionReserva(${r.id})">
                <i class="bi bi-clock-history"></i> Agregar extensión
            </button>`);
    }

    if (r.puede_finalizar) {
        items.push(`
            <button class="item-accion item-accion-exito" onclick="window.finalizarReserva(${r.id})">
                <i class="bi bi-check-circle"></i> Finalizar
            </button>`);
    }

    if (r.puede_cancelar) {
        items.push(`
            <button class="item-accion item-accion-peligro" onclick="window.cancelarReserva(${r.id})">
                <i class="bi bi-x-circle"></i> Cancelar
            </button>`);
    }

    // El menú se inyecta en el body, no dentro del trigger
    const menuId = `menu-reserva-${r.id}`;

    return `
        <div class="acciones-dropdown">
            <button class="btn-acciones-trigger"
                onclick="window.toggleAcciones(this, '${menuId}')"
                data-menu-id="${menuId}">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
        </div>
        <div class="acciones-menu" id="${menuId}" style="display:none;">
            ${items.join('')}
        </div>`;
}

window.toggleAcciones = function (btn, menuId) {
    const menu    = document.getElementById(menuId);
    const abierto = menu.style.display === 'block';

    // Cierra todos
    cerrarTodosLosMenus();

    if (abierto) return;

    // Posicionar con fixed según el botón
    const rect        = btn.getBoundingClientRect();
    const alturaMenu  = menu.offsetHeight || 300; // estimado antes de mostrar
    const espacioAbajo = window.innerHeight - rect.bottom;
    const espacioArriba = rect.top;

    menu.style.display = 'block';

    // Recalcular altura real ya visible
    const alturaReal = menu.offsetHeight;

    // Decidir si abre hacia arriba o hacia abajo
    if (espacioAbajo < alturaReal && espacioArriba > alturaReal) {
        // Abre hacia arriba
        menu.style.top  = (rect.top - alturaReal + window.scrollY) + 'px';
    } else {
        // Abre hacia abajo
        menu.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
    }

    // Alinear a la derecha del botón
    const leftMenu = rect.right - menu.offsetWidth;
    menu.style.left = Math.max(8, leftMenu) + 'px';

    btn.classList.add('activo');
};

function cerrarTodosLosMenus() {
    document.querySelectorAll('.acciones-menu').forEach(m => m.style.display = 'none');
    document.querySelectorAll('.btn-acciones-trigger').forEach(b => b.classList.remove('activo'));
}

// Cierra al hacer click fuera
document.addEventListener('click', function (e) {
    if (!e.target.closest('.acciones-dropdown') && !e.target.closest('.acciones-menu')) {
        cerrarTodosLosMenus();
    }
});

// Cierra al hacer scroll
window.addEventListener('scroll', cerrarTodosLosMenus, true);

window.verReserva = function (id) {
    cerrarTodosLosMenus();

    document.getElementById('verReservaId').textContent = `#${id}`;
    document.getElementById('verCargando').style.display  = 'block';
    document.getElementById('verContenido').style.display = 'none';

    const modal = new Modal(document.getElementById('modalVer'));
    modal.show();

    fetch(`/reservas/${id}`, {
        headers: { 
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(r => {
            console.log('RESPUESTA SHOW:', r);
            // Cabecera
            document.getElementById('verTipo').textContent      = r.tipo_estadia;
            document.getElementById('verEntrada').textContent   = r.fecha_entrada;
            document.getElementById('verSalida').textContent    = r.fecha_salida;
            document.getElementById('verUsuario').textContent   = r.registrado_por;
            document.getElementById('verCreatedAt').textContent = r.created_at;
            document.getElementById('verObservacion').textContent = r.observacion;

            // Badge estado
            const estadoBadge = {
                pendiente:  'badge-estado-reservada',
                activa:     'badge-estado-disponible',
                finalizada: 'badge-estado-limpieza',
                cancelada:  'badge-estado-mantenimiento',
            };
            const badge = document.getElementById('verEstadoBadge');
            badge.className   = `badge-estado ${estadoBadge[r.estado] ?? ''}`;
            badge.textContent = r.estado.charAt(0).toUpperCase() + r.estado.slice(1);

            // Habitaciones
            const habsEl = document.getElementById('verHabitaciones');
            habsEl.innerHTML = r.habitaciones.map(h => `
                <div class="ver-fila">
                    <span class="ver-fila-label">N° ${h.numero} · ${h.tipo}</span>
                    <span class="ver-fila-valor">
                        S/ ${h.precio_aplicado}
                        ${h.horas ? `<span class="ver-tag">${h.horas}h</span>` : ''}
                    </span>
                </div>`).join('');

            // Huéspedes
            const huespEl = document.getElementById('verHuespedes');
            huespEl.innerHTML = r.huespedes.map(h => `
                <div class="ver-fila">
                    <span class="ver-fila-label">${h.nombre}</span>
                    <span class="ver-fila-valor">
                        <span class="ver-tag">${h.tipo_doc}</span>
                        ${h.num_doc}
                        ${h.telefono !== '—' ? `· ${h.telefono}` : ''}
                    </span>
                </div>`).join('');

            // Pagos
            const pagosEl = document.getElementById('verPagos');
            if (r.pagos.length === 0) {
                pagosEl.innerHTML = '<p class="ver-vacio">Sin pagos registrados.</p>';
            } else {
                pagosEl.innerHTML = r.pagos.map(p => `
                    <div class="ver-fila">
                        <span class="ver-fila-label">
                            ${p.fecha}
                            <span class="ver-tag">${p.tipo}</span>
                            <span class="ver-tag">${p.metodo}</span>
                        </span>
                        <span class="ver-fila-valor">S/ ${p.monto}</span>
                    </div>`).join('');
            }

            // Extensiones
            const extSeccion = document.getElementById('verSeccionExtensiones');
            const extEl      = document.getElementById('verExtensiones');
            if (r.extensiones.length === 0) {
                extSeccion.style.display = 'none';
            } else {
                extSeccion.style.display = 'block';
                extEl.innerHTML = r.extensiones.map(e => `
                    <div class="ver-extension">
                        <div class="ver-extension-header">
                            <span><i class="bi bi-clock"></i> +${e.cantidad}h · ${e.fecha}</span>
                            ${e.pago ? `<span>S/ ${e.pago.monto} · ${e.pago.metodo}</span>` : ''}
                        </div>
                        <div class="ver-extension-habs">
                            ${e.habitaciones.map(h =>
                                `<span class="ver-tag">N°${h.numero} · S/${h.monto}</span>`
                            ).join('')}
                        </div>
                    </div>`).join('');
            }

            // Saldo
            document.getElementById('verMontoTotal').textContent  = `S/ ${r.monto_total}`;
            document.getElementById('verMontoPagado').textContent = `S/ ${r.monto_pagado}`;
            const saldoEl = document.getElementById('verSaldo');
            saldoEl.textContent  = `S/ ${r.saldo}`;
            saldoEl.style.color  = parseFloat(r.saldo) > 0 ? '#dc3545' : '#198754';

            document.getElementById('verCargando').style.display  = 'none';
            document.getElementById('verContenido').style.display = 'block';
        })
        .catch(err => {
            console.error('Error cargando reserva:', err);
            document.getElementById('verCargando').innerHTML = 
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la reserva.';
        });
};

// ─── CHECK-IN ───
let checkinReservaId = null;

window.checkinReserva = function (id) {
    cerrarTodosLosMenus();
    checkinReservaId = id;

    document.getElementById('checkinReservaId').textContent      = `#${id}`;
    document.getElementById('checkinCargando').style.display     = 'block';
    document.getElementById('checkinContenido').style.display    = 'none';
    document.getElementById('checkinBtnConfirmar').style.display = 'none';
    document.getElementById('checkinMetodoGrupo').style.display  = 'none';
    document.getElementById('checkinErrorMetodo').style.display  = 'none';

    const modal = new Modal(document.getElementById('modalCheckin'));
    modal.show();

    fetch(`/reservas/${id}/checkin-info`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('checkinCargando').innerHTML =
                    `<i class="bi bi-exclamation-circle"></i> ${resp.error}`;
                return;
            }

            // Habitaciones
            const habsEl = document.getElementById('checkinHabitaciones');
            habsEl.innerHTML = '';
            resp.habitaciones.forEach(h => {
                const div = document.createElement('div');
                div.className = 'ver-fila';
                if (h.disponible) {
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero}</span>
                        <span class="badge-estado badge-estado-disponible">Disponible</span>`;
                } else {
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero}</span>
                        <span class="ver-fila-valor" style="color:#dc3545;">
                            <i class="bi bi-clock"></i> Disponible a las ${h.disponible_a}
                        </span>`;
                }
                habsEl.appendChild(div);
            });

            // Aviso
            const recargoEl = document.getElementById('checkinRecargo');
            if (!resp.todas_libres) {
                recargoEl.innerHTML     = `<i class="bi bi-info-circle"></i> Una o más habitaciones no están disponibles aún.`;
                recargoEl.className     = 'aviso-franja franja-horas';
                recargoEl.style.display = 'block';
            } else if (resp.es_por_horas && resp.es_anticipada) {
                recargoEl.innerHTML = `
                    <i class="bi bi-info-circle-fill"></i>
                    El cliente llega antes de su hora reservada. La habitación está libre.<br>
                    Si hace check-in ahora, la salida será a las <strong>${resp.nueva_salida}</strong>.
                    Sin costo adicional.`;
                recargoEl.className     = 'aviso-franja franja-intermedio';
                recargoEl.style.display = 'block';
            } else if (resp.hay_recargo) {
                recargoEl.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Ingreso anticipado — se cobrará recargo de <strong>S/ ${resp.recargo.toFixed(2)}</strong> (2 horas por habitación).`;
                recargoEl.className     = 'aviso-franja franja-early';
                recargoEl.style.display = 'block';
                document.getElementById('checkinMetodoGrupo').style.display = 'block';
            } else {
                recargoEl.innerHTML     = `<i class="bi bi-check-circle-fill"></i> Habitaciones libres — sin recargo adicional.`;
                recargoEl.className     = 'aviso-franja franja-normal';
                recargoEl.style.display = 'block';
            }

            document.getElementById('checkinCargando').style.display  = 'none';
            document.getElementById('checkinContenido').style.display = 'block';

            if (resp.todas_libres) {
                document.getElementById('checkinBtnConfirmar').style.display = 'inline-flex';
            }
        })
        .catch(err => {
            console.error('Error checkin-info:', err);
            document.getElementById('checkinCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la información.';
        });
};

window.confirmarCheckin = function () {
    const metodo = document.getElementById('checkinMetodoId').value;
    const btn    = document.getElementById('checkinBtnConfirmar');

    const recargoVisible = document.getElementById('checkinMetodoGrupo').style.display !== 'none';
    if (recargoVisible && !metodo) {
        document.getElementById('checkinErrorMetodo').style.display = 'block';
        return;
    }
    document.getElementById('checkinErrorMetodo').style.display = 'none';

    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Procesando...';

    const body = recargoVisible ? { metodo_id: metodo } : {};

    fetch(`/reservas/${checkinReservaId}/checkin`, {
        method: 'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                alert(resp.error);
                btn.disabled  = false;
                btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Confirmar Check-in';
                return;
            }
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Confirmar Check-in';
            Modal.getInstance(document.getElementById('modalCheckin')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Check-in realizado correctamente.');
        })
        .catch(err => {
            console.error('Error checkin:', err);
            alert('Ocurrió un error al realizar el check-in.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Confirmar Check-in';
        });
};


// ─── REGISTRAR PAGO ───
let pagoReservaActualId  = null;
let pagoSaldoActual      = 0;
let pagoMontoTotalActual = 0;

window.pagoReserva = function (id) {
    cerrarTodosLosMenus();

    pagoReservaActualId = id;

    document.getElementById('pagoReservaId').textContent      = `#${id}`;
    document.getElementById('pagoCargando').style.display     = 'block';
    document.getElementById('pagoContenido').style.display    = 'none';
    document.getElementById('pagoBtnConfirmar').style.display = 'none';

    // Limpiar estado previo
    document.getElementById('pagoMonto').value             = '';
    document.getElementById('pagoMetodoId').value          = '';
    document.getElementById('pagoErrorMonto').style.display = 'none';
    document.getElementById('pagoAvisoTipo').style.display  = 'none';

    const modal = new Modal(document.getElementById('modalPago'));
    modal.show();

    // Reutilizamos el endpoint show() para obtener saldos
    fetch(`/reservas/${id}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(r => {
            pagoMontoTotalActual = parseFloat(r.monto_total.replace(',', '')) || 0;
            const montoPagado    = parseFloat(r.monto_pagado.replace(',', '')) || 0;
            pagoSaldoActual      = parseFloat(r.saldo.replace(',', ''))       || 0;

            document.getElementById('pagoMontoTotal').textContent  = `S/ ${r.monto_total}`;
            document.getElementById('pagoMontoPagado').textContent = `S/ ${r.monto_pagado}`;
            document.getElementById('pagoSaldo').textContent       = `S/ ${r.saldo}`;
            document.getElementById('pagoMaximoLabel').textContent = `(máximo S/ ${r.saldo})`;

            // Pre-llenar con el saldo completo
            document.getElementById('pagoMonto').value = pagoSaldoActual.toFixed(2);
            document.getElementById('pagoMonto').max   = pagoSaldoActual;

            actualizarAvisoPago();

            document.getElementById('pagoCargando').style.display     = 'none';
            document.getElementById('pagoContenido').style.display    = 'block';
            document.getElementById('pagoBtnConfirmar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando saldo:', err);
            document.getElementById('pagoCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la reserva.';
        });
};

window.validarMontoPagoModal = function () {
    const monto   = parseFloat(document.getElementById('pagoMonto').value) || 0;
    const errorEl = document.getElementById('pagoErrorMonto');

    if (monto <= 0) {
        errorEl.textContent   = 'El monto debe ser mayor a S/ 0.00';
        errorEl.style.display = 'block';
    } else if (monto > pagoSaldoActual + 0.009) {
        errorEl.textContent   = `No puede superar el saldo pendiente de S/ ${pagoSaldoActual.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }

    actualizarAvisoPago();
};

function actualizarAvisoPago() {
    const monto   = parseFloat(document.getElementById('pagoMonto').value) || 0;
    const avisoEl = document.getElementById('pagoAvisoTipo');

    if (monto <= 0) {
        avisoEl.style.display = 'none';
        return;
    }

    const esFinal = Math.abs(monto - pagoSaldoActual) < 0.01;

    if (esFinal) {
        avisoEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> Pago final — quedará sin saldo pendiente.';
        avisoEl.className   = 'aviso-franja franja-normal';
    } else {
        avisoEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Adelanto — quedará un saldo de S/ ${(pagoSaldoActual - monto).toFixed(2)}.`;
        avisoEl.className   = 'aviso-franja franja-early';
    }
    avisoEl.style.display = 'block';
}

window.confirmarPago = function () {
    const monto   = parseFloat(document.getElementById('pagoMonto').value) || 0;
    const metodo  = document.getElementById('pagoMetodoId').value;

    if (monto <= 0 || monto > pagoSaldoActual + 0.009) {
        document.getElementById('pagoErrorMonto').style.display = 'block';
        document.getElementById('pagoErrorMonto').textContent   =
            `Ingrese un monto válido (máximo S/ ${pagoSaldoActual.toFixed(2)})`;
        return;
    }

    if (!metodo) {
        alert('Seleccione un método de pago.');
        return;
    }

    const btn = document.getElementById('pagoBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    fetch(`/reservas/${pagoReservaActualId}/pago`, {
        method: 'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ monto, metodo_id: metodo }),
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                alert(resp.error);
                btn.disabled  = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Pago';
                return;
            }

            Modal.getInstance(document.getElementById('modalPago')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Pago registrado correctamente.');
        })
        .catch(err => {
            console.error('Error registrando pago:', err);
            alert('Ocurrió un error al registrar el pago.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Pago';
        });
};

// ─── EDITAR FECHAS/TIPO ───────────────────────────────────────
let efReservaId      = null;
let efHabitaciones   = [];     // [{numero, precio_hora_raw, precio_noche_raw}]
let efMontoPagado    = 0;
let efFranjaActual   = '';
let efNuevoTotalCalc = 0;

// Reutilizamos las mismas constantes horarias del modal crear
const EF_CHECKIN_NORMAL = 13 * 60;
const EF_EARLY_INICIO   = 6 * 60 + 1;
const EF_EARLY_FIN      = 11 * 60;
const EF_MADRUGADA_FIN  = 6 * 60;

window.editarFechasReserva = function (id) {
    cerrarTodosLosMenus();
    efReservaId = id;

    document.getElementById('efReservaId').textContent      = `#${id}`;
    document.getElementById('efCargando').style.display     = 'block';
    document.getElementById('efContenido').style.display    = 'none';
    document.getElementById('efBtnGuardar').style.display   = 'none';
    document.getElementById('efResumen').style.display      = 'none';
    document.getElementById('efAvisoFranja').style.display  = 'none';

    const modal = new Modal(document.getElementById('modalEditarFechas'));
    modal.show();

    fetch(`/reservas/${id}/editar-fechas-info`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('efCargando').innerHTML =
                    `<i class="bi bi-exclamation-circle"></i> ${resp.error}`;
                return;
            }

            efHabitaciones = resp.habitaciones;
            efMontoPagado  = resp.monto_pagado;

            // Precargar campos
            document.getElementById('efTipoEstadiaId').value = resp.tipo_estadia_id;
            document.getElementById('efFechaEntrada').value  = resp.fecha_entrada;
            document.getElementById('efFechaSalida').value   = resp.fecha_salida;
            document.getElementById('efObservacion').value   = resp.observacion;

            // Detectar franja inicial
            const entrada  = new Date(resp.fecha_entrada);
            const horaMin  = entrada.getHours() * 60 + entrada.getMinutes();
            efFranjaActual = resp.tipo_estadia === 'noches'
                ? efDetectarFranja(horaMin)
                : 'horas';
            
            // Setear mínimo de fecha entrada = ahora
            const ahoraMin = new Date();
            ahoraMin.setSeconds(0, 0);
            document.getElementById('efFechaEntrada').min = toLocalDateTimeString(ahoraMin);
            efRecalcular();

            document.getElementById('efCargando').style.display  = 'none';
            document.getElementById('efContenido').style.display = 'block';
            document.getElementById('efBtnGuardar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando editar-fechas-info:', err);
            document.getElementById('efCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la información.';
        });
};

// ── Helpers de franja (misma lógica que el modal crear) ──
function efDetectarFranja(horaEnMinutos) {
    if (horaEnMinutos >= 0 && horaEnMinutos <= EF_MADRUGADA_FIN) return 'madrugada';
    if (horaEnMinutos >= EF_EARLY_INICIO && horaEnMinutos <= EF_EARLY_FIN) return 'early';
    if (horaEnMinutos > EF_EARLY_FIN && horaEnMinutos < EF_CHECKIN_NORMAL) return 'intermedio';
    return 'normal';
}

function efTipoNombre() {
    const sel = document.getElementById('efTipoEstadiaId');
    return sel.options[sel.selectedIndex]?.dataset?.nombre ?? '';
}

window.efOnTipoChange = function () {
    // Al cambiar tipo, resetear fechas y franja
    document.getElementById('efFechaEntrada').value = '';
    document.getElementById('efFechaSalida').value  = '';
    efFranjaActual = '';
    document.getElementById('efAvisoFranja').style.display = 'none';
    document.getElementById('efResumen').style.display     = 'none';
};

window.efOnEntradaChange = function () {
    const tipo    = efTipoNombre();
    const entrada = document.getElementById('efFechaEntrada').value;
    if (!tipo || !entrada) return;

    // Validar que no sea pasada
    const ahora = new Date();
    ahora.setSeconds(0, 0);
    let entradaDt = new Date(entrada);
    if (entradaDt < ahora) {
        entradaDt = ahora;
    }

    // Redondear al siguiente múltiplo de 10 minutos (igual que modal crear)
    const mins = entradaDt.getMinutes();
    if (mins % 10 !== 0) {
        const redondeado = Math.ceil(mins / 10) * 10;
        if (redondeado === 60) {
            entradaDt.setHours(entradaDt.getHours() + 1, 0, 0, 0);
        } else {
            entradaDt.setMinutes(redondeado, 0, 0);
        }
    }

    // Actualizar el input con el valor redondeado
    document.getElementById('efFechaEntrada').value = toLocalDateTimeString(entradaDt);

    const horaMin = entradaDt.getHours() * 60 + entradaDt.getMinutes();

    if (tipo === 'noches') {
        efFranjaActual = efDetectarFranja(horaMin);
        const salida = efCalcularCheckout(entradaDt, efFranjaActual);
        document.getElementById('efFechaSalida').value = toLocalDateTimeString(salida);
    } else {
        efFranjaActual = 'horas';
        const salidaMin = new Date(entradaDt.getTime() + 2 * 3600000);
        document.getElementById('efFechaSalida').value = toLocalDateTimeString(salidaMin);
    }

    efMostrarAviso();
    efRecalcular();
};

window.efOnSalidaChange = function () {
    const tipo    = efTipoNombre();
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;

    if (!tipo || !entrada || !salida) return;

    if (tipo === 'noches') {
        // Forzar salida a las 11:00
        const dt = new Date(salida);
        dt.setHours(11, 0, 0, 0);
        document.getElementById('efFechaSalida').value = toLocalDateTimeString(dt);
    }

    efMostrarAviso();
    efRecalcular();
};

function efCalcularCheckout(entrada, franja) {
    const salida = new Date(entrada);
    if (franja === 'madrugada') {
        salida.setHours(11, 0, 0, 0);
    } else {
        salida.setDate(salida.getDate() + 1);
        salida.setHours(11, 0, 0, 0);
    }
    return salida;
}

function efMostrarAviso() {
    const aviso   = document.getElementById('efAvisoFranja');
    const tipo    = efTipoNombre();
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;

    if (!tipo || !entrada || !salida) { aviso.style.display = 'none'; return; }

    const entradaDt = new Date(entrada);
    const salidaDt  = new Date(salida);
    let texto = '';

    if (tipo === 'horas') {
        const horas = Math.round((salidaDt - entradaDt) / 3600000);
        texto = `Estadía por horas — Se cobrarán ${horas === 1 ? '1 hora' : horas + ' horas'}.`;
        aviso.className = 'aviso-franja franja-horas';
    } else {
        const entDia  = new Date(entradaDt.getFullYear(), entradaDt.getMonth(), entradaDt.getDate());
        const salDia  = new Date(salidaDt.getFullYear(), salidaDt.getMonth(), salidaDt.getDate());
        const diff    = Math.round((salDia - entDia) / 86400000);
        const noches  = efFranjaActual === 'madrugada'
            ? (diff === 0 ? 1 : diff + 1)
            : (diff < 1 ? 1 : diff);
        const nText   = noches === 1 ? '1 noche' : `${noches} noches`;
        const msgs    = {
            madrugada:  `Ingreso en madrugada — Se cobra ${nText}. Check out: 11:00 AM.`,
            early:      `Ingreso temprano — Se cobra ${nText} + recargo 2 horas. Check out: 11:00 AM.`,
            intermedio: `Ingreso intermedio — Se cobra ${nText} sin recargo. Check out: 11:00 AM.`,
            normal:     `Se cobra ${nText}. Check out: 11:00 AM.`,
        };
        texto = msgs[efFranjaActual] ?? '';
        aviso.className = `aviso-franja franja-${efFranjaActual}`;
    }

    aviso.textContent   = texto;
    aviso.style.display = texto ? 'block' : 'none';
}

// ── Recálculo central ──
function efRecalcular() {
    const tipo    = efTipoNombre();
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;

    if (!tipo || !entrada || !salida || efHabitaciones.length === 0) {
        document.getElementById('efResumen').style.display = 'none';
        return;
    }

    const entradaDt = new Date(entrada);
    const salidaDt  = new Date(salida);
    let nuevoTotal  = 0;

    efHabitaciones.forEach(h => {
        if (tipo === 'horas') {
            const horas = Math.round((salidaDt - entradaDt) / 3600000);
            nuevoTotal += h.precio_hora_raw * horas;
        } else {
            const entDia = new Date(entradaDt.getFullYear(), entradaDt.getMonth(), entradaDt.getDate());
            const salDia = new Date(salidaDt.getFullYear(), salidaDt.getMonth(), salidaDt.getDate());
            const diff   = Math.round((salDia - entDia) / 86400000);
            const noches = efFranjaActual === 'madrugada'
                ? (diff === 0 ? 1 : diff + 1)
                : (diff < 1 ? 1 : diff);
            nuevoTotal += h.precio_noche_raw * noches;
            if (efFranjaActual === 'early') {
                nuevoTotal += h.precio_hora_raw * 2;
            }
        }
    });

    efNuevoTotalCalc = Math.round(nuevoTotal * 100) / 100;
    const diferencia = Math.round((efNuevoTotalCalc - efMontoPagado) * 100) / 100;
    const minimo50   = Math.round(efNuevoTotalCalc * 0.5 * 100) / 100;

    // Mostrar resumen
    document.getElementById('efNuevoTotal').textContent  = `S/ ${efNuevoTotalCalc.toFixed(2)}`;
    document.getElementById('efMontoPagado').textContent = `S/ ${efMontoPagado.toFixed(2)}`;

    const saldoEl    = document.getElementById('efSaldo');
    const labelEl    = document.getElementById('efSaldoLabel');
    const avisoEl    = document.getElementById('efAvisoCaso');
    const pagoGrupo  = document.getElementById('efPagoGrupo');

    // ── Caso A: nuevo total mayor y pagado < 50% ──
    if (efNuevoTotalCalc > efMontoPagado && efMontoPagado < minimo50) {
        const minimoReq = Math.round((minimo50 - efMontoPagado) * 100) / 100;
        labelEl.textContent   = 'Saldo pendiente';
        saldoEl.textContent   = `S/ ${diferencia.toFixed(2)}`;
        saldoEl.style.color   = '#dc3545';

        avisoEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i>
            El nuevo total requiere un pago adicional mínimo de <strong>S/ ${minimoReq.toFixed(2)}</strong>
            para cubrir el 50%.`;
        avisoEl.className     = 'aviso-franja franja-early';
        avisoEl.style.display = 'block';

        document.getElementById('efPagoMinimoLabel').textContent =
            `(mínimo S/ ${minimoReq.toFixed(2)}, máximo S/ ${diferencia.toFixed(2)})`;
        document.getElementById('efPagoMonto').min   = minimoReq;
        document.getElementById('efPagoMonto').max   = diferencia;
        document.getElementById('efPagoMonto').value = minimoReq.toFixed(2);
        document.getElementById('efPagoError').style.display = 'none';
        pagoGrupo.style.display = 'block';

    // ── Caso B: nuevo total mayor, ya cubre el 50% ──
    } else if (efNuevoTotalCalc > efMontoPagado && efMontoPagado >= minimo50) {
        labelEl.textContent   = 'Saldo pendiente';
        saldoEl.textContent   = `S/ ${diferencia.toFixed(2)}`;
        saldoEl.style.color   = '#dc3545';

        avisoEl.innerHTML     = `<i class="bi bi-check-circle-fill"></i>
            El pago cubre el mínimo requerido. Se guardará con saldo pendiente para el check-in.`;
        avisoEl.className     = 'aviso-franja franja-normal';
        avisoEl.style.display = 'block';
        pagoGrupo.style.display = 'none';

    // ── Caso C: nuevo total menor que lo pagado ──
    } else if (efNuevoTotalCalc < efMontoPagado) {
        const credito = Math.round((efMontoPagado - efNuevoTotalCalc) * 100) / 100;
        labelEl.textContent   = 'Crédito a favor';
        saldoEl.textContent   = `S/ ${credito.toFixed(2)}`;
        saldoEl.style.color   = '#198754';

        avisoEl.innerHTML     = `<i class="bi bi-info-circle-fill"></i>
            El nuevo total es menor a lo pagado. El último pago será ajustado automáticamente.`;
        avisoEl.className     = 'aviso-franja franja-intermedio';
        avisoEl.style.display = 'block';
        pagoGrupo.style.display = 'none';

    // ── Caso D: igual ──
    } else {
        labelEl.textContent   = 'Saldo pendiente';
        saldoEl.textContent   = 'S/ 0.00';
        saldoEl.style.color   = '#198754';

        avisoEl.innerHTML     = `<i class="bi bi-check-circle-fill"></i>
            El total no cambia. Se guardará sin modificar los pagos.`;
        avisoEl.className     = 'aviso-franja franja-normal';
        avisoEl.style.display = 'block';
        pagoGrupo.style.display = 'none';
    }

    document.getElementById('efResumen').style.display = 'block';
}

window.efValidarMonto = function () {
    const monto   = parseFloat(document.getElementById('efPagoMonto').value) || 0;
    const min     = parseFloat(document.getElementById('efPagoMonto').min) || 0;
    const max     = parseFloat(document.getElementById('efPagoMonto').max) || 0;
    const errorEl = document.getElementById('efPagoError');

    if (monto < min) {
        errorEl.textContent   = `El mínimo es S/ ${min.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else if (monto > max) {
        errorEl.textContent   = `No puede superar S/ ${max.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }
};

window.efGuardar = function () {
    const tipo    = document.getElementById('efTipoEstadiaId').value;
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;

    if (!tipo || !entrada || !salida) {
        alert('Complete todos los campos obligatorios.');
        return;
    }

    // Validar pago si Caso A (grupo visible)
    const pagoGrupoVisible = document.getElementById('efPagoGrupo').style.display !== 'none';
    if (pagoGrupoVisible) {
        const monto  = parseFloat(document.getElementById('efPagoMonto').value) || 0;
        const metodo = document.getElementById('efPagoMetodo').value;
        const min    = parseFloat(document.getElementById('efPagoMonto').min) || 0;
        const max    = parseFloat(document.getElementById('efPagoMonto').max) || 0;

        if (monto < min || monto > max) {
            document.getElementById('efPagoError').style.display = 'block';
            return;
        }
        if (!metodo) {
            alert('Seleccione un método de pago.');
            return;
        }
    }

    const btn = document.getElementById('efBtnGuardar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const payload = {
        tipo_estadia_id: tipo,
        fecha_entrada:   entrada,
        fecha_salida:    salida,
        observacion:     document.getElementById('efObservacion').value,
        franja:          efFranjaActual || 'normal',
    };

    if (pagoGrupoVisible) {
        payload.monto_pago = parseFloat(document.getElementById('efPagoMonto').value);
        payload.metodo_id  = document.getElementById('efPagoMetodo').value;
    }

    fetch(`/reservas/${efReservaId}/editar-fechas`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(payload),
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';

            if (resp.error) {
                alert(resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalEditarFechas')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Reserva actualizada correctamente.');
        })
        .catch(err => {
            console.error('Error guardando fechas:', err);
            alert('Ocurrió un error al guardar los cambios.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
        });
};

// ─── REASIGNAR HABITACIÓN ─────────────────────────────────────
let raReservaId          = null;
let raHabsData           = [];   // [{numero, tipo_id, tipo_nombre, precio_aplicado, horas, alternativas}]
let raCambios            = {};   // { numeroActual: numeroNuevo | null }
let raActualSeleccionado = null;

window.reasignarReserva = function (id) {
    cerrarTodosLosMenus();
    raReservaId          = id;
    raHabsData           = [];
    raCambios            = {};
    raActualSeleccionado = null;

    document.getElementById('raReservaId').textContent     = `#${id}`;
    document.getElementById('raCargando').style.display    = 'block';
    document.getElementById('raContenido').style.display   = 'none';
    document.getElementById('raBtnGuardar').style.display  = 'none';
    document.getElementById('raAvisoSinCambios').style.display  = 'none';
    document.getElementById('raMapaAlternativas').style.display = 'none';
    document.getElementById('raSinAlternativas').style.display  = 'none';

    const modal = new Modal(document.getElementById('modalReasignar'));
    modal.show();

    fetch(`/reservas/${id}/reasignar-info`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('raCargando').innerHTML =
                    `<i class="bi bi-exclamation-circle"></i> ${resp.error}`;
                return;
            }

            raHabsData = resp.habitaciones;

            // Inicializar cambios en null (sin cambio)
            raHabsData.forEach(h => {
                raCambios[h.numero] = null;
            });

            // Info rango
            document.getElementById('raInfoRango').innerHTML =
                `<i class="bi bi-calendar-range"></i>
                 ${resp.fecha_entrada} → ${resp.fecha_salida}
                 <span style="margin-left:10px; opacity:0.7;">${raUcfirst(resp.tipo_estadia)}</span>`;

            raRenderizarActuales();

            // Seleccionar la primera habitación por defecto
            if (raHabsData.length > 0) {
                window.raSeleccionarActual(raHabsData[0].numero);
            }

            document.getElementById('raCargando').style.display  = 'none';
            document.getElementById('raContenido').style.display = 'block';
            document.getElementById('raBtnGuardar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando reasignar-info:', err);
            document.getElementById('raCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la información.';
        });
};

function raUcfirst(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

// ── Tarjetas de habitaciones actuales (selector) ──
function raRenderizarActuales() {
    const contenedor = document.getElementById('raHabsActuales');
    contenedor.innerHTML = '';

    raHabsData.forEach(h => {
        const cambio = raCambios[h.numero];
        const activa = raActualSeleccionado === h.numero;

        const div = document.createElement('div');
        div.className = `tarjeta-habitacion ${activa ? 'tarjeta-seleccionada' : ''}`;
        div.id        = `ra-actual-${h.numero}`;
        div.onclick   = () => window.raSeleccionarActual(h.numero);

        div.innerHTML = `
            <div class="tarjeta-numero">N° ${h.numero}</div>
            <div class="tarjeta-tipo">${h.tipo_nombre}</div>
            <div class="tarjeta-precio">
                ${cambio
                    ? `<i class="bi bi-arrow-right"></i> Pasa a N° ${cambio}`
                    : `S/ ${h.precio_aplicado}`}
            </div>`;

        contenedor.appendChild(div);
    });
}

// ── Selección de habitación actual a reasignar ──
window.raSeleccionarActual = function (numero) {
    raActualSeleccionado = numero;
    raRenderizarActuales();
    raRenderizarMapa(numero);
};

// ── Mapa de alternativas por piso (igual a paso2) ──
function raRenderizarMapa(numeroActual) {
    const habActual = raHabsData.find(h => h.numero === numeroActual);

    const mapaWrap   = document.getElementById('raMapaAlternativas');
    const sinAlt     = document.getElementById('raSinAlternativas');
    const contenedor = document.getElementById('raMapaContenedor');

    document.getElementById('raNumeroActual').textContent = numeroActual;

    if (!habActual.alternativas || habActual.alternativas.length === 0) {
        mapaWrap.style.display = 'none';
        sinAlt.style.display   = 'block';
        return;
    }

    sinAlt.style.display   = 'none';
    mapaWrap.style.display = 'block';
    contenedor.innerHTML   = '';

    // Agrupar por piso (igual que habitacionesDisponibles)
    const pisos = {};
    habActual.alternativas.forEach(alt => {
        const piso = Math.floor(alt.numero / 100);
        if (!pisos[piso]) pisos[piso] = [];
        pisos[piso].push(alt);
    });

    const seleccionActual = raCambios[numeroActual];

    Object.keys(pisos).sort((a, b) => a - b).forEach(piso => {
        const seccion = document.createElement('div');
        seccion.style.marginBottom = '16px';

        seccion.innerHTML = `
            <div class="piso-label">Piso ${piso}</div>
            <div class="piso-habitaciones">
                ${pisos[piso].map(alt => `
                    <div class="tarjeta-habitacion ${seleccionActual === alt.numero ? 'tarjeta-seleccionada' : ''}"
                        onclick="window.raToggleAlternativa(${numeroActual}, ${alt.numero})">
                        <div class="tarjeta-numero">N° ${alt.numero}</div>
                        <div class="tarjeta-tipo">${alt.tipo_nombre}</div>
                    </div>
                `).join('')}
            </div>`;

        contenedor.appendChild(seccion);
    });
}

// ── Toggle de alternativa seleccionada ──
window.raToggleAlternativa = function (numeroActual, numeroAlt) {
    raCambios[numeroActual] = (raCambios[numeroActual] === numeroAlt) ? null : numeroAlt;

    raRenderizarActuales();
    raRenderizarMapa(numeroActual);
    document.getElementById('raAvisoSinCambios').style.display = 'none';
};

// ── Guardar ──
window.raGuardar = function () {
    const cambios = Object.entries(raCambios)
        .filter(([de, a]) => a !== null)
        .map(([de, a]) => ({ de: parseInt(de), a }));

    if (cambios.length === 0) {
        document.getElementById('raAvisoSinCambios').style.display = 'block';
        return;
    }

    const btn = document.getElementById('raBtnGuardar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    fetch(`/reservas/${raReservaId}/reasignar`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ cambios }),
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';

            if (resp.error) {
                alert(resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalReasignar')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Habitaciones reasignadas correctamente.');
        })
        .catch(err => {
            console.error('Error reasignando:', err);
            alert('Ocurrió un error al reasignar las habitaciones.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
        });
};


// ─── EDITAR HUÉSPEDES ──────────────────────────────────────────
let huespedModalReservaId  = null;
let huespedModalActuales   = [];   // [{id, nombre, tipo_doc, num_doc, telefono}]
let huespedModalMaxPermitido = 0;
 
window.huespedесReserva = function (id) {
    cerrarTodosLosMenus();
    huespedModalReservaId = id;
 
    // Reset UI
    document.getElementById('huespedReservaId').textContent     = `#${id}`;
    document.getElementById('huespedCargando').style.display    = 'block';
    document.getElementById('huespedContenido').style.display   = 'none';
    document.getElementById('huespedBtnGuardar').style.display  = 'none';
    limpiarBuscadorHuespedModalInterno();
 
    const modal = new Modal(document.getElementById('modalHuespedes'));
    modal.show();
 
    fetch(`/reservas/${id}/huespedes-info`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('huespedCargando').innerHTML =
                    `<i class="bi bi-exclamation-circle"></i> ${resp.error}`;
                return;
            }
 
            huespedModalActuales     = resp.huespedes.map(h => ({
                id:       h.id,
                nombre:   h.nombre,
                tipoDoc:  h.tipo_doc,
                numDoc:   h.num_doc,
                telefono: h.telefono,
            }));
            huespedModalMaxPermitido = resp.max_permitido;
 
            renderizarSeleccionadosModal();
            actualizarContadorModal();
 
            document.getElementById('huespedCargando').style.display   = 'none';
            document.getElementById('huespedContenido').style.display  = 'block';
            document.getElementById('huespedBtnGuardar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando huéspedes:', err);
            document.getElementById('huespedCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar los huéspedes.';
        });
};
 
// ─── BUSCADOR DEL MODAL ────────────────────────────────────────
window.buscarHuespedModal = function () {
    const tipoDoc = document.getElementById('huespedTipoDoc').value;
    const numDoc  = document.getElementById('huespedNumDoc').value.trim();
    const nombre  = document.getElementById('huespedNombre').value.trim();
 
    const params = new URLSearchParams();
    if (tipoDoc && numDoc) {
        params.set('tipo_doc_id', tipoDoc);
        params.set('num_doc', numDoc);
    } else if (nombre) {
        params.set('nombre', nombre);
    } else {
        alert('Ingrese tipo + número de documento, o un nombre para buscar.');
        return;
    }
 
    fetch(`/huespedes/buscar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(resp => {
            const listaEl = document.getElementById('huespedListaResultados');
            const resEl   = document.getElementById('huespedResultados');
            const vacioEl = document.getElementById('huespedVacio');
 
            listaEl.innerHTML = '';
 
            if (!resp.data || resp.data.length === 0) {
                resEl.style.display   = 'none';
                vacioEl.style.display = 'block';
                return;
            }
 
            vacioEl.style.display = 'none';
            resEl.style.display   = 'block';
 
            resp.data.forEach(h => {
                const yaEsta          = huespedModalActuales.some(s => s.id === h.id);
                const limiteAlcanzado = huespedModalActuales.length >= huespedModalMaxPermitido
                                        && huespedModalMaxPermitido > 0;
                const deshabilitado   = yaEsta || (limiteAlcanzado && !yaEsta);
 
                const item = document.createElement('div');
                item.className = 'paso3-item';
                item.innerHTML = `
                    <span class="huesped-nombre">${h.nombre}</span>
                    <span class="huesped-doc">${h.tipo_doc.toUpperCase()}</span>
                    <span class="huesped-doc">${h.num_doc}</span>
                    <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                        ${h.telefono !== '—' ? h.telefono : '—'}
                    </span>
                    <button type="button"
                        class="btn-agregar-huesped ${yaEsta ? 'btn-agregar-ya' : ''} ${limiteAlcanzado && !yaEsta ? 'btn-agregar-limite' : ''}"
                        onclick="window.agregarHuespedModal(${h.id},'${escaparTexto(h.nombre)}','${escaparTexto(h.tipo_doc)}','${escaparTexto(h.num_doc)}','${escaparTexto(h.telefono)}')"
                        ${deshabilitado ? 'disabled' : ''}>
                        <i class="bi bi-${yaEsta ? 'check-lg' : 'person-plus'}"></i>
                        ${yaEsta ? 'Agregado' : 'Agregar'}
                    </button>`;
                listaEl.appendChild(item);
            });
        });
};
 
window.agregarHuespedModal = function (id, nombre, tipoDoc, numDoc, telefono) {
    if (huespedModalActuales.some(h => h.id === id)) return;
    if (huespedModalMaxPermitido > 0 && huespedModalActuales.length >= huespedModalMaxPermitido) return;
 
    huespedModalActuales.push({ id, nombre, tipoDoc, numDoc, telefono });
    renderizarSeleccionadosModal();
    actualizarContadorModal();
 
    // Deshabilitar el botón en resultados
    document.getElementById('huespedListaResultados')
        .querySelectorAll('button').forEach(btn => {
            if (btn.getAttribute('onclick')?.includes(`agregarHuespedModal(${id},`)) {
                btn.disabled  = true;
                btn.classList.add('btn-agregar-ya');
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Agregado';
            }
        });
};
 
window.quitarHuespedModal = function (id) {
    // Mínimo 1 huésped
    if (huespedModalActuales.length <= 1) {
        alert('La reserva debe tener al menos un huésped.');
        return;
    }
 
    huespedModalActuales = huespedModalActuales.filter(h => h.id !== id);
    renderizarSeleccionadosModal();
    actualizarContadorModal();
 
    // Rehabilitar en resultados si está visible
    document.getElementById('huespedListaResultados')
        .querySelectorAll('button').forEach(btn => {
            const match = btn.getAttribute('onclick')?.match(/agregarHuespedModal\((\d+),/);
            if (match && parseInt(match[1]) === id) {
                btn.disabled  = false;
                btn.classList.remove('btn-agregar-ya');
                btn.innerHTML = '<i class="bi bi-person-plus"></i> Agregar';
            }
        });
};
 
window.limpiarBuscadorHuespedModal = function () {
    limpiarBuscadorHuespedModalInterno();
};
 
function limpiarBuscadorHuespedModalInterno() {
    document.getElementById('huespedTipoDoc').value              = '';
    document.getElementById('huespedNumDoc').value               = '';
    document.getElementById('huespedNombre').value               = '';
    document.getElementById('huespedListaResultados').innerHTML  = '';
    document.getElementById('huespedResultados').style.display   = 'none';
    document.getElementById('huespedVacio').style.display        = 'none';
}
 
function actualizarContadorModal() {
    const total  = huespedModalActuales.length;
    const limite = huespedModalMaxPermitido;
    const badge  = document.getElementById('huespedContadorBadge');
    const aviso  = document.getElementById('huespedAvisoLimite');
 
    if (badge) {
        badge.textContent      = limite > 0 ? `${total} / ${limite}` : total;
        badge.style.background = (limite > 0 && total >= limite)
            ? 'linear-gradient(135deg, #dc3545, #bb2d3b)'
            : 'linear-gradient(135deg, var(--dorado), var(--dorado-oscuro))';
    }
 
    if (aviso) {
        if (limite > 0 && total >= limite) {
            aviso.textContent   = `Límite alcanzado: máximo ${limite} huésped${limite !== 1 ? 'es' : ''} para las habitaciones de esta reserva.`;
            aviso.style.display = 'block';
        } else {
            aviso.style.display = 'none';
        }
    }
}
 
function renderizarSeleccionadosModal() {
    const contenedor = document.getElementById('huespedListaSeleccionados');
    const seccion    = document.getElementById('huespedSeleccionados');
 
    contenedor.innerHTML = '';
 
    if (huespedModalActuales.length === 0) {
        seccion.style.display = 'none';
        return;
    }
 
    seccion.style.display = 'block';
 
    huespedModalActuales.forEach(h => {
        const esElUltimo = huespedModalActuales.length === 1;
        const item = document.createElement('div');
        item.className = 'paso3-seleccionado-item';
        item.innerHTML = `
            <span class="huesped-nombre">${h.nombre}</span>
            <span class="huesped-doc">${h.tipoDoc.toUpperCase()}</span>
            <span class="huesped-doc">${h.numDoc}</span>
            <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                ${h.telefono !== '—' ? h.telefono : '—'}
            </span>
            <button type="button"
                class="btn-quitar-huesped ${esElUltimo ? 'btn-quitar-disabled' : ''}"
                onclick="window.quitarHuespedModal(${h.id})"
                title="${esElUltimo ? 'Debe quedar al menos un huésped' : 'Quitar'}"
                ${esElUltimo ? 'disabled' : ''}>
                <i class="bi bi-x-lg"></i>
            </button>`;
        contenedor.appendChild(item);
    });
}
 
// ─── GUARDAR CAMBIOS ───────────────────────────────────────────
window.guardarHuespedes = function () {
    if (huespedModalActuales.length === 0) {
        alert('La reserva debe tener al menos un huésped.');
        return;
    }
 
    const btn = document.getElementById('huespedBtnGuardar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';
 
    fetch(`/reservas/${huespedModalReservaId}/huespedes`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ huespedes: huespedModalActuales.map(h => h.id) }),
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                alert(resp.error);
                btn.disabled  = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
                return;
            }
 
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
            Modal.getInstance(document.getElementById('modalHuespedes')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Huéspedes actualizados correctamente.');
        })
        .catch(err => {
            console.error('Error guardando huéspedes:', err);
            alert('Ocurrió un error al guardar los huéspedes.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
        });
};

// ─── AGREGAR EXTENSIÓN ────────────────────────────────────────
let extReservaId      = null;
let extTipoEstadia    = null;   // 'horas' | 'noches'
let extMontoCalculado = 0;
let extHabsDisponibles = [];    // [numero, ...]
 
window.extensionReserva = function (id) {
    cerrarTodosLosMenus();
    extReservaId = id;
 
    // Reset completo
    document.getElementById('extReservaId').textContent      = `#${id}`;
    document.getElementById('extCargando').style.display     = 'none';
    document.getElementById('extFaseA').style.display        = 'block';
    document.getElementById('extFaseB').style.display        = 'none';
    document.getElementById('extFaseC').style.display        = 'none';
    document.getElementById('extBtnConfirmar').style.display = 'none';
    document.getElementById('extCantidad').value             = '1';
    document.getElementById('extMetodoId').value             = '';
    document.getElementById('extErrorMetodo').style.display  = 'none';
    document.getElementById('extAvisoConflicto').style.display = 'none';
    document.getElementById('extHabitaciones').innerHTML     = '';
    extMontoCalculado  = 0;
    extHabsDisponibles = [];
 
    // Obtener tipo de estadía desde el dataset que ya tenemos en window
    // Lo detectamos en la primera verificación (respuesta del servidor)
    extTipoEstadia = null;
 
    const modal = new Modal(document.getElementById('modalExtension'));
    modal.show();
 
    // Ajustar label según tipo — lo hacemos tras abrir con un fetch ligero
    // al mismo endpoint con cantidad=1 para saber el tipo
    fetch(`/reservas/${id}/extension-info?cantidad=1`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('extFaseA').innerHTML =
                    `<div class="aviso-franja franja-horas"><i class="bi bi-exclamation-circle"></i> ${resp.error}</div>`;
                return;
            }
            extTipoEstadia = resp.tipo_estadia;
            const unidad   = extTipoEstadia === 'horas' ? 'horas' : 'noches';
            document.getElementById('extTipoLabel').textContent =
                `Estadía por ${unidad} — salida actual: ${resp.salida_actual}`;
            document.getElementById('extCantidadLabel').textContent =
                `Cantidad de ${unidad} a extender`;
        })
        .catch(() => {});
};
 
window.extLimpiarResultado = function () {
    document.getElementById('extFaseB').style.display        = 'none';
    document.getElementById('extFaseC').style.display        = 'none';
    document.getElementById('extBtnConfirmar').style.display = 'none';
    extMontoCalculado  = 0;
    extHabsDisponibles = [];
};
 
window.extVerificar = function () {
    const cantidad = parseInt(document.getElementById('extCantidad').value) || 0;
 
    if (cantidad < 1) {
        alert('La cantidad mínima es 1.');
        return;
    }
 
    document.getElementById('extCargando').style.display     = 'block';
    document.getElementById('extFaseB').style.display        = 'none';
    document.getElementById('extFaseC').style.display        = 'none';
    document.getElementById('extBtnConfirmar').style.display = 'none';
 
    fetch(`/reservas/${extReservaId}/extension-info?cantidad=${cantidad}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            document.getElementById('extCargando').style.display = 'none';
 
            if (resp.error) {
                alert(resp.error);
                return;
            }
 
            extTipoEstadia    = resp.tipo_estadia;
            extMontoCalculado = resp.monto_total;
            extHabsDisponibles = resp.habitaciones
                .filter(h => h.disponible)
                .map(h => h.numero);
 
            // Info salida
            document.getElementById('extInfoSalida').innerHTML =
                `<i class="bi bi-arrow-right-circle-fill"></i>
                 Nueva salida estimada: <strong>${resp.nueva_salida}</strong>
                 <span style="opacity:0.7; margin-left:8px;">(+${resp.unidad_label})</span>`;
 
            // Renderizar habitaciones
            const habsEl = document.getElementById('extHabitaciones');
            habsEl.innerHTML = '';
 
            resp.habitaciones.forEach(h => {
                const div = document.createElement('div');
                div.className = 'ver-fila';
 
                if (h.disponible) {
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero} <span class="ver-tag">${h.tipo}</span></span>
                        <span class="ver-fila-valor" style="display:flex; align-items:center; gap:8px;">
                            <span style="color:#198754; font-size:0.82rem;">
                                <i class="bi bi-check-circle-fill"></i> Disponible
                            </span>
                            <strong>S/ ${h.monto.toFixed(2)}</strong>
                        </span>`;
                } else {
                    const msgConflicto = h.estado_conflicto === 'activa'
                        ? `Ocupada por reserva #${h.reserva_id} (activa)`
                        : `Reservada por reserva #${h.reserva_id} (pendiente)`;
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero} <span class="ver-tag">${h.tipo}</span></span>
                        <span class="ver-fila-valor" style="color:#dc3545; font-size:0.82rem;">
                            <i class="bi bi-x-circle-fill"></i> ${msgConflicto}
                        </span>`;
                }
 
                habsEl.appendChild(div);
            });
 
            // Aviso conflictos
            const hayConflictos = resp.habitaciones.some(h => !h.disponible);
            const avisoEl = document.getElementById('extAvisoConflicto');
            if (hayConflictos && resp.hay_disponibles) {
                avisoEl.innerHTML = '<i class="bi bi-info-circle"></i> Algunas habitaciones tienen conflicto y no serán extendidas. Solo se extenderán las disponibles.';
                avisoEl.style.display = 'block';
            } else if (hayConflictos && !resp.hay_disponibles) {
                avisoEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Todas las habitaciones tienen conflicto. No es posible extender esta reserva con esta cantidad.';
                avisoEl.style.display = 'block';
            } else {
                avisoEl.style.display = 'none';
            }
 
            document.getElementById('extFaseB').style.display = 'block';
 
            // Fase C y botón confirmar solo si hay disponibles
            if (resp.hay_disponibles) {
                document.getElementById('extMontoTotal').textContent = `S/ ${resp.monto_total.toFixed(2)}`;
                document.getElementById('extFaseC').style.display        = 'block';
                document.getElementById('extBtnConfirmar').style.display = 'inline-flex';
            }
        })
        .catch(err => {
            console.error('Error verificando extensión:', err);
            document.getElementById('extCargando').style.display = 'none';
            alert('Ocurrió un error al verificar la disponibilidad.');
        });
};
 
window.extConfirmar = function () {
    const metodo   = document.getElementById('extMetodoId').value;
    const cantidad = parseInt(document.getElementById('extCantidad').value) || 0;
 
    if (!metodo) {
        document.getElementById('extErrorMetodo').style.display = 'block';
        return;
    }
    document.getElementById('extErrorMetodo').style.display = 'none';
 
    if (extHabsDisponibles.length === 0) {
        alert('No hay habitaciones disponibles para extender.');
        return;
    }
 
    const btn = document.getElementById('extBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';
 
    fetch(`/reservas/${extReservaId}/extension`, {
        method: 'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            cantidad:      cantidad,
            metodo_id:     metodo,
            habitaciones:  extHabsDisponibles,
            monto:         extMontoCalculado,
        }),
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Extensión';
 
            if (resp.error) {
                alert(resp.error);
                return;
            }
 
            Modal.getInstance(document.getElementById('modalExtension')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Extensión registrada correctamente.');
        })
        .catch(err => {
            console.error('Error confirmando extensión:', err);
            alert('Ocurrió un error al confirmar la extensión.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Extensión';
        });
};

// ─── FINALIZAR ────────────────────────────────────────────────
let finReservaId     = null;
let finEstadosHabs   = {};   // { numero: 'limpieza' | 'mantenimiento' | null }
 
window.finalizarReserva = function (id) {
    cerrarTodosLosMenus();
    finReservaId   = id;
    finEstadosHabs = {};
 
    document.getElementById('finReservaId').textContent      = `#${id}`;
    document.getElementById('finCargando').style.display     = 'block';
    document.getElementById('finContenido').style.display    = 'none';
    document.getElementById('finBtnConfirmar').style.display = 'none';
    document.getElementById('finAvisoIncompleto').style.display = 'none';
 
    const modal = new Modal(document.getElementById('modalFinalizar'));
    modal.show();
 
    // Reutilizamos show() para obtener las habitaciones de la reserva
    fetch(`/reservas/${id}`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('finCargando').innerHTML =
                    `<i class="bi bi-exclamation-circle"></i> ${resp.error}`;
                return;
            }
 
            // Inicializar estado null para cada habitación
            resp.habitaciones.forEach(h => {
                finEstadosHabs[h.numero] = null;
            });
 
            finRenderizarHabs(resp.habitaciones);
 
            document.getElementById('finCargando').style.display  = 'none';
            document.getElementById('finContenido').style.display = 'block';
            document.getElementById('finBtnConfirmar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando reserva para finalizar:', err);
            document.getElementById('finCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la reserva.';
        });
};
 
function finRenderizarHabs(habitaciones) {
    const contenedor = document.getElementById('finHabitaciones');
    contenedor.innerHTML = '';
 
    habitaciones.forEach(h => {
        const div = document.createElement('div');
        div.className    = 'ver-fila';
        div.id           = `fin-fila-${h.numero}`;
        div.style.alignItems = 'center';
        div.innerHTML = `
            <span class="ver-fila-label" style="flex:1;">
                N° ${h.numero}
                <span class="ver-tag">${h.tipo}</span>
            </span>
            <div style="display:flex; gap:6px;">
                <button type="button"
                    id="fin-btn-limpieza-${h.numero}"
                    class="btn-estado-hab"
                    onclick="window.finSeleccionar(${h.numero}, 'limpieza')">
                    <i class="bi bi-stars"></i> Limpieza
                </button>
                <button type="button"
                    id="fin-btn-mantenimiento-${h.numero}"
                    class="btn-estado-hab"
                    onclick="window.finSeleccionar(${h.numero}, 'mantenimiento')">
                    <i class="bi bi-wrench"></i> Mantenimiento
                </button>
            </div>`;
        contenedor.appendChild(div);
    });
}
 
window.finSeleccionar = function (numero, estado) {
    finEstadosHabs[numero] = estado;
 
    // Actualizar visual del par de botones de esa habitación
    const btnLimpieza      = document.getElementById(`fin-btn-limpieza-${numero}`);
    const btnMantenimiento = document.getElementById(`fin-btn-mantenimiento-${numero}`);
 
    if (estado === 'limpieza') {
        btnLimpieza.classList.add('btn-estado-activo-limpieza');
        btnMantenimiento.classList.remove('btn-estado-activo-mantenimiento');
    } else {
        btnMantenimiento.classList.add('btn-estado-activo-mantenimiento');
        btnLimpieza.classList.remove('btn-estado-activo-limpieza');
    }
 
    // Ocultar aviso si se estaba mostrando
    document.getElementById('finAvisoIncompleto').style.display = 'none';
};
 
window.finAplicarTodas = function (estado) {
    Object.keys(finEstadosHabs).forEach(numero => {
        window.finSeleccionar(parseInt(numero), estado);
    });
};
 
window.finConfirmar = function () {
    // Verificar que todas tienen estado
    const incompletas = Object.values(finEstadosHabs).some(v => v === null);
    if (incompletas) {
        document.getElementById('finAvisoIncompleto').style.display = 'block';
        return;
    }
 
    const habitaciones = Object.entries(finEstadosHabs).map(([numero, estado_destino]) => ({
        numero:         parseInt(numero),
        estado_destino,
    }));
 
    const btn = document.getElementById('finBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';
 
    fetch(`/reservas/${finReservaId}/finalizar`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ habitaciones }),
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar Check-out';
 
            if (resp.error) {
                alert(resp.error);
                return;
            }
 
            Modal.getInstance(document.getElementById('modalFinalizar')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Reserva finalizada correctamente.');
        })
        .catch(err => {
            console.error('Error finalizando reserva:', err);
            alert('Ocurrió un error al finalizar la reserva.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar Check-out';
        });
};

// ─── CANCELAR ────────────────────────────────────────────────
let cancelarReservaId = null;

window.cancelarReserva = function (id) {
    cerrarTodosLosMenus();
    cancelarReservaId = id;

    document.getElementById('cancelarReservaId').textContent = `#${id}`;

    const modal = new Modal(document.getElementById('modalCancelar'));
    modal.show();
};

window.confirmarCancelar = function () {
    const btn = document.getElementById('cancelarBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Cancelando...';

    fetch(`/reservas/${cancelarReservaId}/cancelar`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Sí, cancelar reserva';

            if (resp.error) {
                alert(resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalCancelar')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Reserva cancelada correctamente.');
        })
        .catch(err => {
            console.error('Error cancelando reserva:', err);
            alert('Ocurrió un error al cancelar la reserva.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Sí, cancelar reserva';
        });
};

// ─── MODALES ───
document.addEventListener('DOMContentLoaded', () => {

    // Modal crear — limpiar al cerrar
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();
        franjaDetectada           = '';
        habitacionesCargadasPara = '';
        pasoActual                = 1;
        habitacionesSeleccionadas = [];
        habitacionesData          = {};
        ocultarAvisoFranja();
        document.getElementById('paso1').style.display        = 'block';
        document.getElementById('paso2').style.display        = 'none';
        document.getElementById('paso2Mapa').style.display    = 'none';
        document.getElementById('paso2Resumen').style.display = 'none';
        document.getElementById('indicador1').classList.add('paso-activo');
        document.getElementById('indicador2').classList.remove('paso-activo');
        document.getElementById('indicador3').classList.remove('paso-activo');
        document.getElementById('indicador4').classList.remove('paso-activo');
        huespedесSeleccionados = [];
        document.getElementById('paso3TipoDoc').value    = '';
        document.getElementById('paso3NumDoc').value     = '';
        document.getElementById('paso3Nombre').value     = '';
        document.getElementById('paso3ListaResultados').innerHTML = '';
        document.getElementById('paso3Resultados').style.display  = 'none';
        document.getElementById('paso3Vacio').style.display       = 'none';
        document.getElementById('paso3Seleccionados').style.display = 'none';
        document.getElementById('paso3').style.display   = 'none';
        document.getElementById('indicador3').classList.remove('paso-activo');
        document.getElementById('btnAnterior').style.display  = 'none';
        window.limpiarBuscadorHuesped();
        paso4MontoTotal  = 0;
        paso4MontoMinimo = 0;
        paso4EsInmediata = false;
        document.getElementById('paso4MontoPago').value        = '';
        document.getElementById('paso4MetodoId').value         = '';
        document.getElementById('paso4FilasDesglose').innerHTML = '';
        document.getElementById('paso4AvisoOcupacion').style.display = 'none';
        document.getElementById('paso4ErrorMonto').style.display     = 'none';
        document.getElementById('paso4').style.display               = 'none';
        document.getElementById('btnConfirmar').style.display        = 'none';
        document.getElementById('btnSiguiente').style.display        = 'inline-flex';
        document.getElementById('indicador4').classList.remove('paso-activo');
    });

    // Modal crear — setear min fecha entrada al abrir
    document.getElementById('modalCrear').addEventListener('show.bs.modal', function () {
        const ahora = new Date();
        ahora.setSeconds(0, 0);
        document.getElementById('fechaEntrada').min = toLocalDateTimeString(ahora);
    });

    // Salida blur — ajustar minutos
    document.getElementById('fechaSalida').addEventListener('blur', function () {
        const fechaEntradaInput = document.getElementById('fechaEntrada');
        const tipo              = tipoEstadiaNombre();

        if (!this.value || !fechaEntradaInput.value || !tipo) return;

        const salida = new Date(this.value);

        if (tipo === 'horas') {
            const entrada = new Date(fechaEntradaInput.value);
            salida.setMinutes(entrada.getMinutes(), 0, 0);
            this.value = toLocalDateTimeString(salida);
            mostrarAvisoFranja('horas');
        } else {
            salida.setMinutes(0, 0, 0);
            this.value = toLocalDateTimeString(salida);
            mostrarAvisoFranja(franjaDetectada);
        }
    });

    // Paso 3 — buscar con Enter
    ['paso3NumDoc', 'paso3Nombre'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); window.buscarHuesped(); }
        });
    });

    document.getElementById('modalHuespedes').addEventListener('hidden.bs.modal', function () {
        huespedModalReservaId    = null;
        huespedModalActuales     = [];
        huespedModalMaxPermitido = 0;
        limpiarBuscadorHuespedModalInterno();
        document.getElementById('huespedListaSeleccionados').innerHTML = '';
        document.getElementById('huespedSeleccionados').style.display  = 'none';
        document.getElementById('huespedAvisoLimite').style.display    = 'none';
        document.getElementById('huespedBtnGuardar').style.display     = 'none';
        document.getElementById('huespedCargando').style.display       = 'block';
        document.getElementById('huespedContenido').style.display      = 'none';
    });
 
    // Enter en buscador del modal
    ['huespedNumDoc', 'huespedNombre'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); window.buscarHuespedModal(); }
        });
    });

    document.getElementById('modalExtension').addEventListener('hidden.bs.modal', function () {
        extReservaId       = null;
        extTipoEstadia     = null;
        extMontoCalculado  = 0;
        extHabsDisponibles = [];
        document.getElementById('extCantidad').value             = '1';
        document.getElementById('extMetodoId').value             = '';
        document.getElementById('extErrorMetodo').style.display  = 'none';
        document.getElementById('extCargando').style.display     = 'none';
        document.getElementById('extFaseA').style.display        = 'block';
        document.getElementById('extFaseB').style.display        = 'none';
        document.getElementById('extFaseC').style.display        = 'none';
        document.getElementById('extBtnConfirmar').style.display = 'none';
        document.getElementById('extHabitaciones').innerHTML     = '';
        document.getElementById('extAvisoConflicto').style.display = 'none';
        document.getElementById('extInfoSalida').innerHTML       = '';
        document.getElementById('extTipoLabel').textContent      = '';
    });

    document.getElementById('modalFinalizar').addEventListener('hidden.bs.modal', function () {
        finReservaId   = null;
        finEstadosHabs = {};
        document.getElementById('finHabitaciones').innerHTML        = '';
        document.getElementById('finAvisoIncompleto').style.display = 'none';
        document.getElementById('finBtnConfirmar').style.display    = 'none';
        document.getElementById('finCargando').style.display        = 'block';
        document.getElementById('finContenido').style.display       = 'none';
    });

    document.getElementById('modalCancelar').addEventListener('hidden.bs.modal', function () {
        cancelarReservaId = null;
    });

    document.getElementById('modalEditarFechas').addEventListener('hidden.bs.modal', function () {
        efReservaId      = null;
        efHabitaciones   = [];
        efMontoPagado    = 0;
        efFranjaActual   = '';
        efNuevoTotalCalc = 0;
        document.getElementById('efFechaEntrada').value         = '';
        document.getElementById('efFechaSalida').value          = '';
        document.getElementById('efObservacion').value          = '';
        document.getElementById('efAvisoFranja').style.display  = 'none';
        document.getElementById('efResumen').style.display      = 'none';
        document.getElementById('efPagoGrupo').style.display    = 'none';
        document.getElementById('efAvisoCaso').style.display    = 'none';
        document.getElementById('efPagoMonto').value            = '';
        document.getElementById('efPagoMetodo').value           = '';
        document.getElementById('efPagoError').style.display    = 'none';
        document.getElementById('efBtnGuardar').style.display   = 'none';
        document.getElementById('efCargando').style.display     = 'block';
        document.getElementById('efContenido').style.display    = 'none';
    });
    
    document.getElementById('modalReasignar').addEventListener('hidden.bs.modal', function () {
        raReservaId          = null;
        raHabsData           = [];
        raCambios            = {};
        raActualSeleccionado = null;
        document.getElementById('raHabsActuales').innerHTML         = '';
        document.getElementById('raMapaContenedor').innerHTML       = '';
        document.getElementById('raMapaAlternativas').style.display = 'none';
        document.getElementById('raSinAlternativas').style.display  = 'none';
        document.getElementById('raAvisoSinCambios').style.display  = 'none';
        document.getElementById('raBtnGuardar').style.display       = 'none';
        document.getElementById('raCargando').style.display         = 'block';
        document.getElementById('raContenido').style.display        = 'none';
    });

    document.getElementById('filtroFechaEntrada').value = HOY_STR;
    // ─── CARGA INICIAL ───
    window.buscarReservas(1, true);
});
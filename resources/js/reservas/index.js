import { Modal } from 'bootstrap';

// ── 01. CONFIG HORARIA Y UTILIDADES GLOBALES ──
const CHECKIN_NORMAL = 13 * 60;
const EARLY_INICIO   = 6 * 60 + 1;
const EARLY_FIN      = 11 * 60;
const MADRUGADA_FIN  = 6 * 60;
const HORAS_MINIMAS  = 2;

let franjaDetectada = '';
let pasoActual      = 1;

// Convierte un Date a string compatible con <input datetime-local>
function toLocalDateTimeString(date) {
    const offset = date.getTimezoneOffset() * 60000;
    const local  = new Date(date.getTime() - offset);
    return local.toISOString().slice(0, 16);
}

// Nombre del tipo de estadía seleccionado (horas/noches)
function tipoEstadiaNombre() {
    return document.getElementById('tipoEstadiaId').value;
}


// ── 02. ALERTAS DE FEEDBACK Y AVISOS SEMÁNTICOS ──

// Muestra un toast de éxito/error tras una acción AJAX
function mostrarAlerta(tipo, mensaje) {
    const clases = { exito: 'alerta-exito', error: 'login-error' };
    const iconos = { exito: 'bi-check-circle', error: 'bi-exclamation-circle' };

    document.querySelectorAll('.alerta-exito, .login-error').forEach(a => a.remove());

    const div = document.createElement('div');
    div.className = `${clases[tipo]} mb-3`;
    div.innerHTML = `<i class="bi ${iconos[tipo]}"></i> ${mensaje}`;

    const filtros = document.querySelector('.filtros-barra');
    filtros.parentNode.insertBefore(div, filtros);

    setTimeout(() => div.remove(), 4000);
}

const ICONOS_AVISO = {
    info:        'bi-info-circle-fill',
    exito:       'bi-check-circle-fill',
    advertencia: 'bi-exclamation-triangle-fill',
    peligro:     'bi-exclamation-octagon-fill',
};

const CLASES_AVISO = {
    info:        'franja-info',
    exito:       'franja-exito',
    advertencia: 'franja-advertencia',
    peligro:     'franja-peligro',
};

// Pinta un .aviso-franja con la categoría semántica indicada (info/exito/advertencia/peligro)
function pintarAviso(elemento, categoria, mensajeHtml, iconoCustom = null) {
    const icono = iconoCustom ?? ICONOS_AVISO[categoria] ?? ICONOS_AVISO.info;
    const clase = CLASES_AVISO[categoria] ?? CLASES_AVISO.info;

    elemento.className     = `aviso-franja ${clase}`;
    elemento.innerHTML     = `<i class="bi ${icono}"></i> ${mensajeHtml}`;
    elemento.style.display = 'block';
}

// Oculta un aviso semántico
function ocultarAviso(elemento) {
    elemento.style.display = 'none';
}


// ── 03. MODAL CREAR — PASO 2: DATOS GENERALES (fechas/tipo de estadía) ──

// Reinicia el paso de Datos al cambiar el tipo de estadía
window.actualizarPaso1 = function () {
    const fechaEntradaInput = document.getElementById('fechaEntrada');
    const fechaSalidaInput  = document.getElementById('fechaSalida');

    fechaEntradaInput.value = '';
    fechaSalidaInput.value  = '';
    fechaSalidaInput.min    = '';

    ocultarAvisoFranja();
    franjaDetectada = '';

    const ahora = new Date();
    ahora.setSeconds(0, 0);
    fechaEntradaInput.min  = toLocalDateTimeString(ahora);
    fechaEntradaInput.step = 600;
    fechaSalidaInput.step  = 3600;
};

// Valida/ajusta la fecha de entrada y calcula salida sugerida + franja
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

// Valida/ajusta la fecha de salida según el tipo y la franja
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

// Determina la franja horaria según la hora de entrada (en minutos)
function detectarFranja(horaEnMinutos) {
    if (horaEnMinutos >= 0 && horaEnMinutos <= MADRUGADA_FIN)          return 'madrugada';
    if (horaEnMinutos >= EARLY_INICIO && horaEnMinutos <= EARLY_FIN)   return 'early';
    if (horaEnMinutos > EARLY_FIN && horaEnMinutos < CHECKIN_NORMAL)   return 'intermedio';
    return 'normal';
}

// Calcula la fecha/hora de checkout según la franja detectada
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

function ocultarAvisoFranja() {
    ocultarAviso(document.getElementById('avisoFranja'));
}

// Muestra el aviso de franja con la categoría semántica correspondiente
function mostrarAvisoFranja(franja) {
    const aviso        = document.getElementById('avisoFranja');
    const fechaEntrada = document.getElementById('fechaEntrada').value;
    const fechaSalida  = document.getElementById('fechaSalida').value;
    const tipo         = tipoEstadiaNombre();

    if (tipo === 'horas') {
        if (!fechaEntrada || !fechaSalida) { ocultarAvisoFranja(); return; }
        const horas      = Math.round((new Date(fechaSalida) - new Date(fechaEntrada)) / 3600000);
        const horasTexto = horas === 1 ? '1 hora' : `${horas} horas`;
        pintarAviso(aviso, 'info', `Estadía por horas — Se cobrarán ${horasTexto}.`);
        return;
    }

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
        madrugada:  { cat: 'info',        texto: `Ingreso en madrugada — Se cobra ${nochesTexto}. Check out: 11:00 AM.` },
        early:      { cat: 'advertencia', texto: `Ingreso temprano — Se cobra ${nochesTexto} + recargo 2 horas. Check out: 11:00 AM.` },
        intermedio: { cat: 'exito',       texto: `Ingreso intermedio — Se cobra ${nochesTexto} sin recargo. Check out: 11:00 AM.` },
        normal:     { cat: 'info',        texto: `Se cobra ${nochesTexto}. Check out: 11:00 AM.` },
    };

    const msg = mensajes[franja];
    if (!msg) { ocultarAvisoFranja(); return; }

    pintarAviso(aviso, msg.cat, msg.texto);
}


// ── 04. MODAL CREAR — NAVEGACIÓN DEL WIZARD ──
// Nuevo orden: Paso 1 = Huéspedes · Paso 2 = Datos · Paso 3 = Habitaciones · Paso 4 = Pagos

// Avanza/retrocede entre pasos, valida el paso actual y dispara carga de datos
window.cambiarPaso = function (direccion) {
    if (window.cambiandoPaso) return;
    if (direccion === 1 && pasoActual === 1 && !validarPaso1()) return;
    if (direccion === 1 && pasoActual === 2 && !validarPaso2()) return;
    if (direccion === 1 && pasoActual === 3 && !validarPaso3()) return;

    document.getElementById(`paso${pasoActual}`).style.display = 'none';
    document.getElementById(`indicador${pasoActual}`).classList.remove('paso-activo');

    pasoActual += direccion;

    document.getElementById(`paso${pasoActual}`).style.display = 'block';
    document.getElementById(`indicador${pasoActual}`).classList.add('paso-activo');

    document.getElementById('btnAnterior').style.display = pasoActual === 1 ? 'none' : 'inline-flex';

    // Paso 3 = Habitaciones: se carga el mapa de disponibilidad al entrar
    if (pasoActual === 3) cargarHabitacionesDisponiblesIfNeeded();

    if (pasoActual === 4) {
        window.inicializarPaso4();
        document.getElementById('btnSiguiente').style.display = 'none';
        document.getElementById('btnConfirmar').style.display = 'inline-flex';
    } else {
        document.getElementById('btnSiguiente').style.display = 'inline-flex';
        document.getElementById('btnConfirmar').style.display = 'none';
    }
};

// Paso 1 = Huéspedes. Sin límite de capacidad aquí (se controla en el paso de Habitaciones).
function validarPaso1() {
    const errorEl = document.getElementById('paso3ErrorGeneral');

    if (huespedesSeleccionados.length === 0) {
        errorEl.textContent   = 'Agregue al menos un huésped.';
        errorEl.style.display = 'block';
        return false;
    }
    if (!huespedesSeleccionados.some(h => h.principal)) {
        errorEl.textContent   = 'Debe marcar un huésped como principal.';
        errorEl.style.display = 'block';
        return false;
    }
    errorEl.style.display = 'none';
    return true;
}

// Paso 2 = Datos (tipo de estadía y fechas)
function validarPaso2() {
    const tipo    = document.getElementById('tipoEstadiaId').value;
    const entrada = document.getElementById('fechaEntrada').value;
    const salida  = document.getElementById('fechaSalida').value;
    const errorEl = document.getElementById('paso1ErrorGeneral');

    if (!tipo || !entrada || !salida) {
        errorEl.style.display = 'block';
        return false;
    }
    errorEl.style.display = 'none';
    return true;
}

// Paso 3 = Habitaciones. Aquí sí se valida la capacidad contra los huéspedes ya agregados.
function validarPaso3() {
    const errorEl = document.getElementById('paso2ErrorSeleccion');
    if (habitacionesSeleccionadas.length === 0) {
        errorEl.style.display = 'block';
        return false;
    }
    errorEl.style.display = 'none';

    if (maxHuespedesPermitido > 0 && huespedesSeleccionados.length > maxHuespedesPermitido) {
        mostrarAlerta('error',
            `La cantidad de huéspedes agregados (${huespedesSeleccionados.length}) supera la capacidad de las habitaciones seleccionadas (${maxHuespedesPermitido}).`);
        return false;
    }

    return true;
}

function validarPaso4() {
    const monto  = parseFloat(document.getElementById('paso4MontoPago').value) || 0;
    const metodo = document.getElementById('paso4MetodoId').value;
    const errorMetodoEl = document.getElementById('paso4ErrorMetodo');
    const errorMontoEl  = document.getElementById('paso4ErrorMonto');

    if (!metodo) {
        errorMetodoEl.style.display = 'block';
        return false;
    }
    errorMetodoEl.style.display = 'none';

    if (monto < paso4MontoMinimo) {
        errorMontoEl.textContent   = `El mínimo es S/ ${paso4MontoMinimo.toFixed(2)}`;
        errorMontoEl.style.display = 'block';
        return false;
    }
    if (monto > paso4MontoTotal) {
        errorMontoEl.textContent   = `No puede superar el total de S/ ${paso4MontoTotal.toFixed(2)}`;
        errorMontoEl.style.display = 'block';
        return false;
    }
    errorMontoEl.style.display = 'none';

    if (!paso4MetodoEsEfectivo()) {
        const numOp = document.getElementById('paso4NumeroOperacion').value.trim();
        if (!numOp) {
            document.getElementById('paso4ErrorNumeroOperacion').style.display = 'block';
            return false;
        }
    }

    return true;
}

// Pide confirmación si el usuario ya avanzó de paso antes de cerrar el wizard
window.confirmarCierreCrear = function () {
    if (pasoActual > 1) {
        if (!confirm('Si cierra ahora perderá los datos ingresados en esta reserva. ¿Desea continuar?')) {
            return;
        }
    }
    Modal.getInstance(document.getElementById('modalCrear')).hide();
};


// ── 05. FILTROS Y BÚSQUEDA (TABLA PRINCIPAL) ──
let paginaActual = 1;
let esVistaInicial   = false;
let controladorBusqueda = null;

// Consulta reservas filtradas y pinta la tabla
window.buscarReservas = function (pagina = 1, esInicial = esVistaInicial) {
    paginaActual = pagina;
    esVistaInicial = esInicial;

    if (controladorBusqueda) controladorBusqueda.abort();
    controladorBusqueda = new AbortController();

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
        signal: controladorBusqueda.signal,
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    })
    .then(resp => {
        const tbody = document.querySelector('#tablaReservas tbody');
        tbody.innerHTML = '';

        if (resp.data.length === 0) {
            const msg = esInicial
                ? 'No hay actividad registrada para hoy.'
                : 'No se encontraron reservas con los filtros aplicados.';
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="paso2-estado">
                        <i class="bi bi-search"></i>
                        ${msg}
                    </td>
                </tr>`;
            document.getElementById('paginacion').classList.remove('activo');
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
    })
    .catch(err => {
        if (err.name === 'AbortError') return;
        console.error('Error buscando reservas:', err);
        mostrarAlerta('error', 'Ocurrió un error al cargar las reservas.');
    });
};

// Limpia la barra de filtros y vuelve a buscar desde la página 1
window.limpiarFiltros = function () {
    document.getElementById('filtroEstado').value       = '';
    document.getElementById('filtroFechaEntrada').value = '';
    document.getElementById('filtroHuesped').value      = '';
    document.getElementById('filtroHabitacion').value   = '';
    window.buscarReservas(1, true);
};


// ── 06. PAGINACIÓN (TABLA PRINCIPAL) ──

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
            onclick="window.buscarReservas(${i})" ${disabled ? 'disabled' : ''}>
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


// ── 07. MODAL CREAR — PASO 3: HABITACIONES ──
let habitacionesSeleccionadas = [];
let habitacionesData          = {};
let habitacionesCargadasPara  = '';

// Evita recargar el mapa si los parámetros (fechas/tipo) no cambiaron
function cargarHabitacionesDisponiblesIfNeeded() {
    const entrada       = document.getElementById('fechaEntrada').value;
    const salida        = document.getElementById('fechaSalida').value;
    const tipoEstadiaId = document.getElementById('tipoEstadiaId').value;

    const firma = `${entrada}|${salida}|${tipoEstadiaId}`;

    if (firma === habitacionesCargadasPara) {
        window.cambiandoPaso = false;
        return;
    }

    habitacionesCargadasPara  = firma;
    habitacionesSeleccionadas = [];
    habitacionesData          = {};

    cargarHabitacionesDisponibles();
}

// Consulta al servidor las habitaciones disponibles y renderiza el mapa
function cargarHabitacionesDisponibles() {
    window.cambiandoPaso = true;

    document.getElementById('paso2Cargando').style.display = 'block';
    document.getElementById('paso2Mapa').style.display     = 'none';
    document.getElementById('paso2Vacio').style.display    = 'none';
    document.getElementById('paso2Resumen').style.display  = 'none';
    ocultarAviso(document.getElementById('paso2AvisoCapacidad'));

    const params = new URLSearchParams({
        fecha_entrada:   document.getElementById('fechaEntrada').value,
        fecha_salida:    document.getElementById('fechaSalida').value,
        tipo_estadia: document.getElementById('tipoEstadiaId').value,
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
        })
        .finally(() => {
            window.cambiandoPaso = false;
        });
}

// Renderiza el mapa de habitaciones agrupadas por piso
function renderizarMapa(pisos, tipoNombre) {
    const mapa = document.getElementById('paso2Mapa');
    mapa.innerHTML = '';

    pisos.forEach(p => {
        const seccion = document.createElement('div');
        seccion.className = 'piso-seccion';

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

// Selecciona/deselecciona una habitación
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

    actualizarAvisoCapacidadHuespedes();
};

// Calcula y muestra el resumen de habitaciones seleccionadas + total
function actualizarResumenPaso2() {
    const resumen = document.getElementById('paso2Resumen');

    if (habitacionesSeleccionadas.length === 0) {
        resumen.style.display = 'none';
        ocultarAviso(document.getElementById('paso2AvisoCapacidad'));
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

// Recordatorio de capacidad: compara huéspedes ya agregados (paso 1) contra la capacidad
// de las habitaciones marcadas en este paso, con color según el estado.
function actualizarAvisoCapacidadHuespedes() {
    const aviso = document.getElementById('paso2AvisoCapacidad');
    if (!aviso) return;

    if (habitacionesSeleccionadas.length === 0) {
        ocultarAviso(aviso);
        return;
    }

    const totalHuespedes = huespedesSeleccionados.length;
    const capacidad      = maxHuespedesPermitido;

    if (totalHuespedes <= capacidad) {
        pintarAviso(aviso, 'exito',
            `Huéspedes ingresados: <strong>${totalHuespedes}</strong> — la capacidad seleccionada (${capacidad}) es suficiente.`);
    } else {
        pintarAviso(aviso, 'advertencia',
            `Huéspedes ingresados: <strong>${totalHuespedes}</strong> — supera la capacidad seleccionada (${capacidad}).`);
    }
}


// ── 08. MODAL CREAR — PASO 1: HUÉSPEDES ──
let huespedesSeleccionados = [];
let maxHuespedesPermitido  = 0;

// Busca huéspedes por documento o nombre
window.buscarHuesped = function () {
    const numDoc  = document.getElementById('paso3NumDoc').value.trim();
    const nombre  = document.getElementById('paso3Nombre').value.trim();
    const errorEl = document.getElementById('paso3ErrorBusqueda');

    const params = new URLSearchParams();
    if (numDoc) {
        params.set('num_doc', numDoc);
    } else if (nombre) {
        params.set('nombre', nombre);
    } else {
        errorEl.style.display = 'block';
        return;
    }
    errorEl.style.display = 'none';

    const btnBuscar = document.getElementById('paso3BtnBuscar');
    btnBuscar.disabled = true;
    const htmlOriginal = btnBuscar.innerHTML;
    btnBuscar.innerHTML = '<i class="bi bi-arrow-repeat"></i> Buscando...';

    fetch(`/huespedes/buscar?${params}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
        .then(res => res.json())
        .then(resp => {
            const listaEl = document.getElementById('paso3ListaResultados');
            const resEl   = document.getElementById('paso3Resultados');
            const vacioEl = document.getElementById('paso3Vacio');
            const btnCrearNuevo = document.getElementById('paso3BtnCrearNuevo');

            listaEl.innerHTML = '';

            if (!resp.data || resp.data.length === 0) {
                resEl.style.display   = 'none';
                vacioEl.style.display = 'block';
                btnCrearNuevo.style.display = 'inline-flex';
                return;
            }

            vacioEl.style.display = 'none';
            btnCrearNuevo.style.display = 'none';
            window.ocultarCrearHuespedInline();
            resEl.style.display   = 'block';

            resp.data.forEach(h => {
                const yaEsta = huespedesSeleccionados.some(s => s.numDoc === h.num_doc);
                const limiteAlcanzado = huespedesSeleccionados.length >= maxHuespedesPermitido && maxHuespedesPermitido > 0;
                const deshabilitado   = yaEsta || (limiteAlcanzado && !yaEsta);

                const item = document.createElement('div');
                item.className = 'paso3-item';
                item.innerHTML = `
                    <span class="huesped-nombre">${h.nombre}</span>
                    <span class="huesped-doc">${h.num_doc}</span>
                    <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                        ${h.telefono !== '—' ? h.telefono : '—'}
                    </span>
                    <button type="button"
                        class="btn-agregar-huesped ${yaEsta ? 'btn-agregar-ya' : ''} ${limiteAlcanzado && !yaEsta ? 'btn-agregar-limite' : ''}"
                        onclick="window.agregarHuesped('${escaparTexto(h.num_doc)}','${escaparTexto(h.nombre)}','${escaparTexto(h.telefono)}')"
                        ${deshabilitado ? 'disabled' : ''}>
                        <i class="bi bi-${yaEsta ? 'check-lg' : 'person-plus'}"></i>
                        ${yaEsta ? 'Agregado' : 'Agregar'}
                    </button>`;
                listaEl.appendChild(item);
            });
        })
        .finally(() => {
            btnBuscar.disabled  = false;
            btnBuscar.innerHTML = htmlOriginal;
        });
};

// Escapa comillas para insertar valores dentro de atributos onclick
function escaparTexto(str) {
    return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Agrega un huésped a la lista de seleccionados del Paso 1
window.agregarHuesped = function (numDoc, nombre, telefono) {
    if (huespedesSeleccionados.some(h => h.numDoc === numDoc)) return;
    if (maxHuespedesPermitido > 0 && huespedesSeleccionados.length >= maxHuespedesPermitido) return;

    const esPrimero = huespedesSeleccionados.length === 0;
    huespedesSeleccionados.push({ numDoc, nombre, telefono, principal: esPrimero });

    renderizarSeleccionados();
    actualizarContadorHuespedes();
    actualizarAvisoCapacidadHuespedes();

    const lista = document.getElementById('paso3ListaResultados');
    lista.querySelectorAll('button').forEach(btn => {
        if (btn.getAttribute('onclick')?.includes(`agregarHuesped('${numDoc}',`)) {
            btn.disabled  = true;
            btn.classList.add('btn-agregar-ya');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Agregado';
        }
    });
};

// Quita un huésped de la lista de seleccionados del Paso 1
window.quitarHuesped = function (numDoc) {
    const eraPrincipal = huespedesSeleccionados.find(h => h.numDoc === numDoc)?.principal;
    huespedesSeleccionados = huespedesSeleccionados.filter(h => h.numDoc !== numDoc);

    if (eraPrincipal && huespedesSeleccionados.length > 0) {
        huespedesSeleccionados[0].principal = true;
    }

    renderizarSeleccionados();
    actualizarContadorHuespedes();
    actualizarAvisoCapacidadHuespedes();

    const lista = document.getElementById('paso3ListaResultados');
    lista.querySelectorAll('button').forEach(btn => {
        const match = btn.getAttribute('onclick')?.match(/agregarHuesped\('([^']+)',/);
        if (match && match[1] === numDoc) {
            btn.disabled  = false;
            btn.classList.remove('btn-agregar-ya');
            btn.innerHTML = '<i class="bi bi-plus-lg"></i> Agregar';
        }
    });
};

window.marcarPrincipal = function (numDoc) {
    huespedesSeleccionados.forEach(h => h.principal = (h.numDoc === numDoc));
    renderizarSeleccionados();
};

window.limpiarBuscadorHuesped = function () {
    document.getElementById('paso3NumDoc').value              = '';
    document.getElementById('paso3Nombre').value              = '';
    document.getElementById('paso3ListaResultados').innerHTML = '';
    document.getElementById('paso3Resultados').style.display  = 'none';
    document.getElementById('paso3Vacio').style.display       = 'none';
    document.getElementById('paso3BtnCrearNuevo').style.display = 'none';
    window.ocultarCrearHuespedInline();
};

// Actualiza el badge contador y el aviso de límite alcanzado
function actualizarContadorHuespedes() {
    const total  = huespedesSeleccionados.length;
    const limite = maxHuespedesPermitido;
    const badge  = document.getElementById('paso3ContadorBadge');
    const aviso  = document.getElementById('paso3AvisoLimite');

    if (badge) {
        badge.textContent = limite > 0 ? `${total} / ${limite}` : total;
        badge.classList.toggle('badge-contador-limite', limite > 0 && total >= limite);
    }

    if (aviso) {
        if (limite > 0 && total >= limite) {
            pintarAviso(aviso, 'advertencia',
                `Límite alcanzado: máximo ${limite} huésped${limite !== 1 ? 'es' : ''} para las habitaciones seleccionadas.`);
        } else {
            ocultarAviso(aviso);
        }
    }
}

// Renderiza la lista de huéspedes seleccionados con botón "Quitar"
function renderizarSeleccionados() {
    const contenedor = document.getElementById('paso3ListaSeleccionados');
    const seccion    = document.getElementById('paso3Seleccionados');

    contenedor.innerHTML = '';
    actualizarContadorHuespedes();
    actualizarEstadoSugerencia();

    if (huespedesSeleccionados.length === 0) {
        seccion.style.display = 'none';
        return;
    }

    actualizarContadorHuespedes();
    seccion.style.display = 'block';

    huespedesSeleccionados.forEach(h => {
        const item = document.createElement('div');
        item.className = 'paso3-seleccionado-item';
        item.innerHTML = `
            <label class="huesped-principal-radio" title="Marcar como huésped principal">
                <input type="radio" name="huespedPrincipal"
                    ${h.principal ? 'checked' : ''}
                    onchange="window.marcarPrincipal('${h.numDoc}')">
            </label>
            <span class="huesped-nombre">${h.nombre}</span>
            <span class="huesped-doc">${h.numDoc}</span>
            <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                ${h.telefono !== '—' ? h.telefono : '—'}
            </span>
            ${h.principal ? '<span class="badge-principal">Principal</span>' : ''}
            <button type="button" class="btn-quitar-huesped"
                onclick="window.quitarHuesped('${h.numDoc}')" title="Quitar">
                <i class="bi bi-x-lg"></i>
            </button>`;
        contenedor.appendChild(item);
    });
}

// Muestra/oculta el bloque de sugerencia y refresca su info al abrir
window.toggleSugerencia = function () {
    const contenedor = document.getElementById('sugerenciaContenedor');
    const abierto     = contenedor.style.display !== 'none';

    contenedor.style.display = abierto ? 'none' : 'block';

    if (!abierto) {
        actualizarInfoSugerencia();
    }
};

// Actualiza el aviso "Se guardará para..." y habilita/deshabilita el botón de sugerencia
function actualizarInfoSugerencia() {
    const info = document.getElementById('sugerenciaInfo');
    const btn  = document.getElementById('sugerenciaBtnGuardar');
    const principal = huespedesSeleccionados.find(h => h.principal);

    if (!principal) {
        ocultarAviso(info);
        btn.disabled = true;
        return;
    }

    pintarAviso(info, 'info', `Se guardará para: <strong>${principal.nombre}</strong> (${principal.numDoc})`);
    btn.disabled = false;
}

// Habilita/deshabilita el botón toggle según haya o no huéspedes,
// y cierra/resetea el panel si la lista se queda en 0.
function actualizarEstadoSugerencia() {
    const btnToggle  = document.getElementById('sugerenciaBtnToggle');
    const contenedor = document.getElementById('sugerenciaContenedor');

    if (huespedesSeleccionados.length === 0) {
        btnToggle.disabled = true;

        if (contenedor.style.display !== 'none') {
            contenedor.style.display = 'none';
            document.getElementById('sugerenciaComentario').value               = '';
            document.getElementById('sugerenciaInfo').style.display             = 'none';
            document.getElementById('sugerenciaErrorComentario').style.display  = 'none';
            document.getElementById('sugerenciaBtnGuardar').disabled            = true;
        }
    } else {
        btnToggle.disabled = false;
    }
}

// Limpia el error de comentario vacío mientras el usuario escribe
window.validarComentarioSugerencia = function () {
    const valor = document.getElementById('sugerenciaComentario').value.trim();
    if (valor) {
        document.getElementById('sugerenciaErrorComentario').style.display = 'none';
    }
};

// Envía la sugerencia/comentario a la tabla independiente (no afecta el flujo de la reserva)
window.guardarSugerencia = function () {
    const principal  = huespedesSeleccionados.find(h => h.principal);
    const comentario = document.getElementById('sugerenciaComentario').value.trim();
    const errorEl    = document.getElementById('sugerenciaErrorComentario');

    if (!principal) return;

    if (!comentario) {
        errorEl.style.display = 'block';
        return;
    }
    errorEl.style.display = 'none';

    const btn = document.getElementById('sugerenciaBtnGuardar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    fetch('/sugerencias', {
        method: 'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ num_doc: principal.numDoc, comentario }),
    })
        .then(res => res.json())
        .then(resp => {
            btn.innerHTML = '<i class="bi bi-save"></i> Guardar sugerencia';
            btn.disabled  = false;

            if (resp.error) {
                mostrarAlerta('error', resp.error);
                return;
            }

            document.getElementById('sugerenciaComentario').value = '';
            mostrarAlerta('exito', 'Sugerencia guardada correctamente.');
        })
        .catch(err => {
            console.error('Error guardando sugerencia:', err);
            mostrarAlerta('error', 'Ocurrió un error al guardar la sugerencia.');
            btn.innerHTML = '<i class="bi bi-save"></i> Guardar sugerencia';
            btn.disabled  = false;
        });
};

// ── 08b. MODAL CREAR — PASO 1: CREACIÓN RÁPIDA DE HUÉSPED (cuando la búsqueda no arroja resultados) ──

// Muestra el mini-formulario y precarga el N° de documento buscado (si se buscó por documento)
window.mostrarCrearHuespedInline = function () {
    const numDocBuscado = document.getElementById('paso3NumDoc').value.trim();
    const nombreBuscado = document.getElementById('paso3Nombre').value.trim();

    document.getElementById('crearHuespedNumDoc').value    = numDocBuscado;
    document.getElementById('crearHuespedNombre').value    = numDocBuscado ? '' : nombreBuscado;
    document.getElementById('crearHuespedTelefono').value  = '';
    document.getElementById('errorCrearHuespedDoc').textContent = '';
    document.getElementById('errorCrearHuespedTel').textContent = '';
    document.getElementById('errorCrearHuespedGeneral').style.display = 'none';
    document.getElementById('crearHuespedNumDoc').closest('.campo-input').classList.remove('error');
    document.getElementById('crearHuespedTelefono').closest('.campo-input').classList.remove('error');

    document.getElementById('paso3Vacio').style.display        = 'none';  
    document.getElementById('paso3CrearHuesped').style.display = 'block';

    if (numDocBuscado) {
        window.verificarDocumentoInline();
    }
};

window.ocultarCrearHuespedInline = function () {
    const el = document.getElementById('paso3CrearHuesped');
    if (el) el.style.display = 'none';
};

window.cancelarCrearHuespedInline = function () {
    window.ocultarCrearHuespedInline();
    document.getElementById('paso3Vacio').style.display = 'block';
};

// Verificación AJAX de documento único, igual que en el módulo Huéspedes
window.verificarDocumentoInline = function () {
    const numDoc     = document.getElementById('crearHuespedNumDoc').value.trim();
    const errorEl    = document.getElementById('errorCrearHuespedDoc');
    const campoInput = document.getElementById('crearHuespedNumDoc').closest('.campo-input');

    if (!numDoc) {
        errorEl.textContent = '';
        campoInput.classList.remove('error');
        return;
    }

    fetch(`/huespedes/verificar-documento?num_doc=${encodeURIComponent(numDoc)}`, {
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

// Verificación AJAX de teléfono único, igual que en el módulo Huéspedes
window.verificarTelefonoInline = function () {
    const telefono   = document.getElementById('crearHuespedTelefono').value.trim();
    const errorEl    = document.getElementById('errorCrearHuespedTel');
    const campoInput = document.getElementById('crearHuespedTelefono').closest('.campo-input');

    if (!telefono) {
        errorEl.textContent = '';
        campoInput.classList.remove('error');
        return;
    }

    fetch(`/huespedes/verificar-telefono?telefono=${encodeURIComponent(telefono)}`, {
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

// Guarda el huésped nuevo directo en BD (AJAX) y lo agrega de inmediato a la reserva
window.guardarHuespedInline = function () {
    const nombre   = document.getElementById('crearHuespedNombre').value.trim();
    const numDoc   = document.getElementById('crearHuespedNumDoc').value.trim();
    const telefono = document.getElementById('crearHuespedTelefono').value.trim();

    const errorGeneralEl = document.getElementById('errorCrearHuespedGeneral');
    const errorDocEl      = document.getElementById('errorCrearHuespedDoc');
    const errorTelEl      = document.getElementById('errorCrearHuespedTel');

    if (!nombre || !numDoc) {
        errorGeneralEl.textContent   = 'Complete el nombre y el número de documento.';
        errorGeneralEl.style.display = 'block';
        return;
    }
    if (errorDocEl.textContent.trim() !== '' || errorTelEl.textContent.trim() !== '') {
        errorGeneralEl.textContent   = 'Corrija los errores marcados antes de guardar.';
        errorGeneralEl.style.display = 'block';
        return;
    }
    errorGeneralEl.style.display = 'none';

    const btn = document.getElementById('btnGuardarHuespedInline');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    fetch('/huespedes/crear-rapido', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ nombre, num_doc: numDoc, telefono: telefono || null }),
    })
        .then(async res => {
            const data = await res.json().catch(() => null);
            return { ok: res.ok, data };
        })
        .then(({ ok, data }) => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Huésped';

            if (!ok) {
                const msg = data?.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data?.error ?? 'Ocurrió un error al guardar el huésped.');
                errorGeneralEl.textContent   = msg;
                errorGeneralEl.style.display = 'block';
                return;
            }

            const h = data.huesped;
            window.agregarHuesped(h.num_doc, h.nombre, h.telefono ?? '—');
            window.ocultarCrearHuespedInline();
            window.limpiarBuscadorHuesped();
            mostrarAlerta('exito', 'Huésped registrado y agregado a la reserva.');
        })
        .catch(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Huésped';
            errorGeneralEl.textContent   = 'Ocurrió un error de conexión al guardar el huésped.';
            errorGeneralEl.style.display = 'block';
        });
};


// ── 09. MODAL CREAR — PASO 4: PAGOS Y CONFIRMACIÓN ──
let paso4MontoTotal  = 0;
let paso4MontoMinimo = 0;
let paso4EsInmediata = false;

// Calcula desglose, total, mínimo de pago y aviso de ocupación inmediata
window.inicializarPaso4 = function () {
    const entrada     = new Date(document.getElementById('fechaEntrada').value);
    const ahora       = new Date();
    const diffMinutos = (entrada - ahora) / 60000;
    paso4EsInmediata  = diffMinutos <= 10;

    const tipo     = tipoEstadiaNombre();
    const fechaEnt = new Date(document.getElementById('fechaEntrada').value);
    const fechaSal = new Date(document.getElementById('fechaSalida').value);

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

    document.getElementById('paso4MinimoLabel').textContent = paso4EsInmediata
        ? '(pago completo requerido)'
        : `(mínimo 50%: S/ ${paso4MontoMinimo.toFixed(2)})`;

    const aviso = document.getElementById('paso4AvisoOcupacion');
    if (paso4EsInmediata) {
        pintarAviso(aviso, 'advertencia', 'Ocupación inmediata — se requiere el pago total antes de ingresar.');
    } else {
        ocultarAviso(aviso);
    }

    document.getElementById('paso4MontoPago').min   = paso4MontoMinimo;
    document.getElementById('paso4MontoPago').max   = paso4MontoTotal;
    document.getElementById('paso4MontoPago').value = paso4MontoMinimo.toFixed(2);
    document.getElementById('paso4ErrorMonto').style.display = 'none';
    document.getElementById('paso4ErrorNumeroOperacion').style.display = 'none';

    const metodoSelect  = document.getElementById('paso4MetodoId');
    const opcionMetodo  = metodoSelect.options[metodoSelect.selectedIndex];
    const esEfectivo    = (opcionMetodo?.dataset.nombre ?? '') === 'efectivo';
    document.getElementById('paso4GrupoNumeroOperacion').style.display =
        (metodoSelect.value !== '' && !esEfectivo) ? 'block' : 'none';
};

function paso4MetodoEsEfectivo() {
    const select = document.getElementById('paso4MetodoId');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.onCambioMetodoPago = function () {
    const grupo = document.getElementById('paso4GrupoNumeroOperacion');
    const select = document.getElementById('paso4MetodoId');

    if (select.value !== '' && !paso4MetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('paso4NumeroOperacion').value = '';
        document.getElementById('paso4ErrorNumeroOperacion').style.display = 'none';
    }
};

window.validarNumeroOperacion = function () {
    const valor   = document.getElementById('paso4NumeroOperacion').value.trim();
    const errorEl = document.getElementById('paso4ErrorNumeroOperacion');
    errorEl.style.display = valor ? 'none' : 'block';
};

window.validarMontoPago = function () {
    const monto   = parseFloat(document.getElementById('paso4MontoPago').value) || 0;
    const errorEl = document.getElementById('paso4ErrorMonto');

    if (monto < paso4MontoMinimo) {
        errorEl.textContent   = `El mínimo es S/ ${paso4MontoMinimo.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else if (monto > paso4MontoTotal) {
        errorEl.textContent   = `No puede superar el total de S/ ${paso4MontoTotal.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }
};

// Envía la reserva completa (fechas, habitaciones, huéspedes, pago) al servidor
window.confirmarReserva = function () {
    const montoIngresadoPrevio = parseFloat(document.getElementById('paso4MontoPago').value) || 0;
    window.inicializarPaso4();

    const montoInput = document.getElementById('paso4MontoPago');
    if (montoIngresadoPrevio >= paso4MontoMinimo && montoIngresadoPrevio <= paso4MontoTotal) {
        montoInput.value = montoIngresadoPrevio.toFixed(2);
    }

    if (!validarPaso4()) {
        mostrarAlerta('error', 'Los montos se actualizaron por el tiempo transcurrido. Verifique el monto a pagar antes de continuar.');
        return;
    }

    const btnConfirmar = document.getElementById('btnConfirmar');
    btnConfirmar.disabled  = true;
    btnConfirmar.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const payload = {
        fecha_entrada:   document.getElementById('fechaEntrada').value,
        fecha_salida:    document.getElementById('fechaSalida').value,
        tipo_estadia: document.getElementById('tipoEstadiaId').value,
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
        huespedes:  huespedesSeleccionados.map(h => h.numDoc),
        huesped_principal:  huespedesSeleccionados.find(h => h.principal)?.numDoc,
        monto_pago: parseFloat(document.getElementById('paso4MontoPago').value),
        metodo_id:  document.getElementById('paso4MetodoId').value,
    };

    if (!paso4MetodoEsEfectivo()) {
        payload.numero_operacion = document.getElementById('paso4NumeroOperacion').value.trim();
    }

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
                mostrarAlerta('error', resp.error);
                btnConfirmar.disabled  = false;
                btnConfirmar.innerHTML = 'Confirmar Reserva';
                return;
            }
            btnConfirmar.disabled  = false;
            btnConfirmar.innerHTML = 'Confirmar Reserva';
            document.getElementById('modalCrear').querySelector('[data-bs-dismiss="modal"]').click();
            window.buscarReservas(1);
            mostrarAlerta('exito', 'Reserva creada correctamente.');
        })
        .catch(err => {
            console.error('FETCH ERROR:', err);
            mostrarAlerta('error', 'Ocurrió un error al guardar la reserva.');
            btnConfirmar.disabled  = false;
            btnConfirmar.innerHTML = 'Confirmar Reserva';
        });
};


// ── 10. DROPDOWN DE ACCIONES POR RESERVA (TABLA PRINCIPAL) ──

// Construye el trigger y el menú flotante de acciones según los permisos de la reserva
function construirAcciones(r) {
    const items = [];

    items.push(`
        <button class="item-accion" onclick="window.verReserva(${r.id})">
            <i class="bi bi-eye"></i> Ver detalle
        </button>`);

    if (r.puede_pago) {
        items.push(`
            <button class="item-accion" onclick="window.pagoReserva(${r.id})">
                <i class="bi bi-cash-coin"></i> Registrar pago
                <span class="item-accion-saldo">S/ ${r.saldo_pendiente.toFixed(2)}</span>
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
                <i class="bi bi-arrow-left-right"></i> Reasignar Habitaciones
            </button>`);
    }

    if (r.puede_huespedes) {
        items.push(`
            <button class="item-accion" onclick="window.huespedesReserva(${r.id})">
                <i class="bi bi-people"></i> Editar huéspedes
            </button>`);
    }

    if (r.puede_checkin) {
        items.push(`
            <button class="item-accion" onclick="window.checkinReserva(${r.id})">
                <i class="bi bi-box-arrow-in-right"></i> Check-in
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

    const menuId = `menu-reserva-${r.id}`;

    return `
        <div class="acciones-dropdown">
            <button class="btn-acciones-trigger"
                onclick="window.toggleAcciones(this, '${menuId}')"
                data-menu-id="${menuId}">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
        </div>
        <div class="acciones-menu" id="${menuId}">
            ${items.join('')}
        </div>`;
}

// Abre/cierra el menú flotante y lo posiciona respecto al botón trigger
window.toggleAcciones = function (btn, menuId) {
    const menu    = document.getElementById(menuId);
    const abierto = menu.classList.contains('activo');

    cerrarTodosLosMenus();

    if (abierto) return;

    const rect          = btn.getBoundingClientRect();
    const espacioAbajo  = window.innerHeight - rect.bottom;
    const espacioArriba = rect.top;

    menu.classList.add('activo');

    const alturaReal = menu.offsetHeight;

    if (espacioAbajo < alturaReal && espacioArriba > alturaReal) {
        menu.style.top = (rect.top - alturaReal + window.scrollY) + 'px';
    } else {
        menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
    }

    const leftMenu = rect.right - menu.offsetWidth;
    menu.style.left = Math.max(8, leftMenu) + 'px';

    btn.classList.add('activo');
};

function cerrarTodosLosMenus() {
    document.querySelectorAll('.acciones-menu').forEach(m => m.classList.remove('activo'));
    document.querySelectorAll('.btn-acciones-trigger').forEach(b => b.classList.remove('activo'));
}

// ── 11. ACCIÓN: VER DETALLE (MODAL VER) ──

// Carga y pinta toda la info de la reserva (habitaciones, huéspedes, pagos, extensiones, saldo)
window.verReserva = function (id) {
    cerrarTodosLosMenus();

    document.getElementById('verReservaId').textContent    = `#${id}`;
    document.getElementById('verCargando').style.display   = 'block';
    document.getElementById('verContenido').style.display  = 'none';

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
            const esPorHoras = r.tipo_estadia === 'Horas';

            document.getElementById('verTipo').textContent        = r.tipo_estadia;
            document.getElementById('verEntrada').textContent     = r.fecha_entrada;
            document.getElementById('verSalida').textContent      = r.fecha_salida;
            document.getElementById('verUsuario').textContent     = r.registrado_por;
            document.getElementById('verCreatedAt').textContent   = r.created_at;
            document.getElementById('verObservacion').textContent = r.observacion;

            const estadoBadge = {
                pendiente:  'badge-estado-reservada',
                activa:     'badge-estado-disponible',
                finalizada: 'badge-estado-limpieza',
                cancelada:  'badge-estado-mantenimiento',
            };
            const badge = document.getElementById('verEstadoBadge');
            badge.className   = `badge-estado ${estadoBadge[r.estado] ?? ''}`;
            badge.textContent = r.estado.charAt(0).toUpperCase() + r.estado.slice(1);

            // Suma monto y cantidad de extensiones por N° de habitación
            const extensionesPorHabitacion = {};
            r.extensiones.forEach(e => {
                e.habitaciones.forEach(h => {
                    if (!extensionesPorHabitacion[h.numero]) {
                        extensionesPorHabitacion[h.numero] = { monto: 0, cantidad: 0 };
                    }
                    extensionesPorHabitacion[h.numero].monto    += parseFloat(h.monto);
                    extensionesPorHabitacion[h.numero].cantidad += e.cantidad;
                });
            });

            const habsEl = document.getElementById('verHabitaciones');
            habsEl.innerHTML = r.habitaciones.map(h => {
                const unidad = esPorHoras
                    ? `${h.tiempo_estadia}h`
                    : `${h.tiempo_estadia} ${h.tiempo_estadia === 1 ? 'noche' : 'noches'}`;

                const ext = extensionesPorHabitacion[h.numero];
                let extraTexto = '';
                if (ext) {
                    const unidadExt = esPorHoras
                        ? `${ext.cantidad}h`
                        : `${ext.cantidad} ${ext.cantidad === 1 ? 'noche' : 'noches'}`;
                    extraTexto = ` + S/ ${ext.monto.toFixed(2)} <span class="ver-tag">${unidadExt}</span>`;
                }

                return `
                <div class="ver-fila">
                    <span class="ver-fila-label">N° ${h.numero} · ${h.tipo}</span>
                    <span class="ver-fila-valor">
                        S/ ${h.precio_aplicado}
                        ${h.tiempo_estadia ? `<span class="ver-tag">${unidad}</span>` : ''}
                        ${extraTexto}
                    </span>
                </div>`;
            }).join('');

            const huespEl = document.getElementById('verHuespedes');
            huespEl.innerHTML = r.huespedes.map(h => `
                <div class="ver-fila">
                    <span class="ver-fila-label">${h.nombre}</span>
                    <span class="ver-fila-valor">
                        ${h.num_doc}
                        · ${h.telefono}
                    </span>
                </div>`).join('');

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
                            ${p.numero_operacion ? `<span class="ver-tag"><i class="bi bi-hash"></i>${p.numero_operacion}</span>` : ''}
                            ${p.comprobante ? `<span class="ver-tag">${p.comprobante.serie}-${p.comprobante.numero}</span>` : ''}
                        </span>
                        <span class="ver-fila-valor">
                            S/ ${p.monto}
                            ${p.comprobante ? `
                                <a href="/pagos/${p.id}/comprobante" target="_blank"
                                    class="btn-ver-comprobante" title="Ver comprobante">
                                    <i class="bi bi-receipt"></i>
                                </a>` : ''}
                        </span>
                    </div>`).join('');
            }

            const extSeccion = document.getElementById('verSeccionExtensiones');
            const extEl      = document.getElementById('verExtensiones');
            if (r.extensiones.length === 0) {
                extSeccion.style.display = 'none';
            } else {
                extSeccion.style.display = 'block';
                extEl.innerHTML = r.extensiones.map(e => {
                    const unidadExt = esPorHoras
                        ? `${e.cantidad}h`
                        : `${e.cantidad} ${e.cantidad === 1 ? 'noche' : 'noches'}`;
                    return `
                    <div class="ver-extension">
                        <div class="ver-extension-header">
                            <span><i class="bi bi-clock"></i> +${unidadExt} · ${e.fecha}</span>
                            ${e.pago ? `<span>S/ ${e.pago.monto} · ${e.pago.metodo}</span>` : ''}
                        </div>
                        <div class="ver-extension-habs">
                            ${e.habitaciones.map(h =>
                                `<span class="ver-tag">N°${h.numero} · S/${h.monto}</span>`
                            ).join('')}
                        </div>
                    </div>`;
                }).join('');
            }

            const devSeccion = document.getElementById('verSeccionDevoluciones');
            const devEl      = document.getElementById('verDevoluciones');
            if (!r.devoluciones || r.devoluciones.length === 0) {
                devSeccion.style.display = 'none';
            } else {
                devSeccion.style.display = 'block';
                devEl.innerHTML = r.devoluciones.map(d => `
                    <div class="ver-fila">
                        <span class="ver-fila-label">
                            ${d.fecha}
                            <span class="ver-tag">${d.origen}</span>
                            <span class="ver-tag">${d.metodo}</span>
                            ${d.numero_operacion ? `<span class="ver-tag">${d.numero_operacion}</span>` : ''}
                        </span>
                        <span class="ver-fila-valor">
                            Devuelto: S/ ${d.monto_devuelto}
                            ${parseFloat(d.monto_retenido) > 0 ? ` · Retenido: S/ ${d.monto_retenido}` : ''}
                        </span>
                    </div>`).join('');
            }

            const compSeccion = document.getElementById('verSeccionComprobante');
            const compEl      = document.getElementById('verComprobante');
            if (!r.comprobante) {
                compSeccion.style.display = 'none';
            } else {
                compSeccion.style.display = 'block';
                const c = r.comprobante;
                compEl.innerHTML = `
                    <div class="ver-fila">
                        <span class="ver-fila-label">
                            <span class="ver-tag">${c.tipo}</span>
                            ${c.serie}-${c.numero}
                            ${c.ruc ? `<span class="ver-tag">RUC ${c.ruc}</span>` : ''}
                        </span>
                        <span class="ver-fila-valor">
                            ${c.razon_social ? c.razon_social : ''}
                            <a href="/reservas/${r.id}/comprobante" target="_blank"
                                class="btn-ver-comprobante" title="Ver comprobante">
                                <i class="bi bi-receipt"></i>
                            </a>
                        </span>
                    </div>`;
            }

            document.getElementById('verMontoTotal').textContent  = `S/ ${r.monto_total}`;
            document.getElementById('verMontoPagado').textContent = `S/ ${r.monto_pagado}`;
            const saldoEl = document.getElementById('verSaldo');
            saldoEl.textContent = `S/ ${r.saldo}`;
            saldoEl.classList.toggle('ver-saldo-positivo', parseFloat(r.saldo) > 0);
            saldoEl.classList.toggle('ver-saldo-cero', parseFloat(r.saldo) <= 0);

            document.getElementById('verCargando').style.display  = 'none';
            document.getElementById('verContenido').style.display = 'block';
        })
        .catch(err => {
            console.error('Error cargando reserva:', err);
            document.getElementById('verCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la reserva.';
        });
};


// ── 12. ACCIÓN: REGISTRAR PAGO (MODAL PAGO) ──
let pagoReservaActualId  = null;
let pagoSaldoActual      = 0;
let pagoMontoTotalActual = 0;

// Abre el modal y carga el saldo actual de la reserva
window.pagoReserva = function (id) {
    cerrarTodosLosMenus();

    pagoReservaActualId = id;

    document.getElementById('pagoReservaId').textContent      = `#${id}`;
    document.getElementById('pagoCargando').style.display     = 'block';
    document.getElementById('pagoContenido').style.display    = 'none';
    document.getElementById('pagoBtnConfirmar').style.display = 'none';

    const btnConfirmar = document.getElementById('pagoBtnConfirmar');
    btnConfirmar.disabled  = false;
    btnConfirmar.innerHTML = 'Confirmar Pago';

    document.getElementById('pagoMonto').value              = '';
    document.getElementById('pagoMetodoId').value           = '';
    document.getElementById('pagoErrorMonto').style.display = 'none';
    document.getElementById('pagoAvisoTipo').style.display   = 'none';
    document.getElementById('pagoNumeroOperacion').value               = '';
    document.getElementById('pagoGrupoNumeroOperacion').style.display  = 'none';
    document.getElementById('pagoErrorNumeroOperacion').style.display  = 'none';

    const modal = new Modal(document.getElementById('modalPago'));
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
            pagoMontoTotalActual = parseFloat(r.monto_total.replace(',', '')) || 0;
            const montoPagado    = parseFloat(r.monto_pagado.replace(',', '')) || 0;
            pagoSaldoActual      = parseFloat(r.saldo.replace(',', ''))       || 0;

            document.getElementById('pagoMontoTotal').textContent  = `S/ ${r.monto_total}`;
            document.getElementById('pagoMontoPagado').textContent = `S/ ${r.monto_pagado}`;
            document.getElementById('pagoSaldo').textContent       = `S/ ${r.saldo}`;
            document.getElementById('pagoMaximoLabel').textContent = `(máximo S/ ${r.saldo})`;

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

// Muestra si el pago será "final" o un "adelanto"
function actualizarAvisoPago() {
    const monto   = parseFloat(document.getElementById('pagoMonto').value) || 0;
    const avisoEl = document.getElementById('pagoAvisoTipo');

    if (monto <= 0) {
        ocultarAviso(avisoEl);
        return;
    }

    const esFinal = Math.abs(monto - pagoSaldoActual) < 0.01;

    if (esFinal) {
        pintarAviso(avisoEl, 'exito', 'Pago final — quedará sin saldo pendiente.');
    } else {
        pintarAviso(avisoEl, 'info', `Adelanto — quedará un saldo de S/ ${(pagoSaldoActual - monto).toFixed(2)}.`);
    }
}

// Envía el pago al servidor
window.confirmarPago = function () {
    const monto  = parseFloat(document.getElementById('pagoMonto').value) || 0;
    const metodo = document.getElementById('pagoMetodoId').value;

    if (monto <= 0 || monto > pagoSaldoActual + 0.009) {
        document.getElementById('pagoErrorMonto').style.display = 'block';
        document.getElementById('pagoErrorMonto').textContent   =
            `Ingrese un monto válido (máximo S/ ${pagoSaldoActual.toFixed(2)})`;
        return;
    }

    if (!metodo) {
        document.getElementById('pagoErrorMetodo').style.display = 'block';
        return;
    }
    document.getElementById('pagoErrorMetodo').style.display = 'none';

    if (!pagoMetodoEsEfectivo()) {
        const numOp = document.getElementById('pagoNumeroOperacion').value.trim();
        if (!numOp) {
            document.getElementById('pagoErrorNumeroOperacion').style.display = 'block';
            return;
        }
    }

    const btn = document.getElementById('pagoBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const body = { monto, metodo_id: metodo };
    if (!pagoMetodoEsEfectivo()) {
        body.numero_operacion = document.getElementById('pagoNumeroOperacion').value.trim();
    }

    fetch(`/reservas/${pagoReservaActualId}/pago`, {
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
                mostrarAlerta('error', resp.error);
                btn.disabled  = false;
                btn.innerHTML = 'Confirmar Pago';
                return;
            }
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Pago';
            Modal.getInstance(document.getElementById('modalPago')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Pago registrado correctamente.');
        })
        .catch(err => {
            console.error('Error registrando pago:', err);
            mostrarAlerta('error', 'Ocurrió un error al registrar el pago.');
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Pago';
        });
};

function pagoMetodoEsEfectivo() {
    const select = document.getElementById('pagoMetodoId');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.onCambioMetodoPagoModal = function () {
    const grupo = document.getElementById('pagoGrupoNumeroOperacion');
    const select = document.getElementById('pagoMetodoId');

    if (select.value !== '' && !pagoMetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('pagoNumeroOperacion').value = '';
        document.getElementById('pagoErrorNumeroOperacion').style.display = 'none';
    }
};

window.validarNumeroOperacionPago = function () {
    const valor = document.getElementById('pagoNumeroOperacion').value.trim();
    document.getElementById('pagoErrorNumeroOperacion').style.display = valor ? 'none' : 'block';
};


// ── 13. ACCIÓN: EDITAR FECHAS/TIPO (MODAL EDITARFECHAS) ──
let efReservaId      = null;
let efHabitaciones   = [];
let efMontoPagado    = 0;
let efRecargoPagado  = 0;
let efFranjaActual   = '';
let efNuevoTotalCalc = 0;
let efCreditoTotal   = 0;
let efDebounceTimer   = null;
let efConflictoActual = false;

const EF_CHECKIN_NORMAL = 13 * 60;
const EF_EARLY_INICIO   = 6 * 60 + 1;
const EF_EARLY_FIN      = 11 * 60;
const EF_MADRUGADA_FIN  = 6 * 60;

// Abre el modal y carga datos actuales de la reserva
window.editarFechasReserva = function (id) {
    cerrarTodosLosMenus();
    efReservaId = id;

    document.getElementById('efReservaId').textContent     = `#${id}`;
    document.getElementById('efCargando').style.display    = 'block';
    document.getElementById('efContenido').style.display   = 'none';
    document.getElementById('efBtnGuardar').style.display  = 'none';
    document.getElementById('efResumen').style.display     = 'none';
    document.getElementById('efAvisoFranja').style.display = 'none';

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

            efHabitaciones  = resp.habitaciones;
            efMontoPagado   = resp.monto_pagado;
            efRecargoPagado = resp.recargo_pagado;

            document.getElementById('efTipoEstadiaId').value = resp.tipo_estadia;
            document.getElementById('efFechaEntrada').value  = resp.fecha_entrada;
            document.getElementById('efFechaSalida').value   = resp.fecha_salida;
            document.getElementById('efObservacion').value   = resp.observacion;

            const entrada = new Date(resp.fecha_entrada);
            const horaMin = entrada.getHours() * 60 + entrada.getMinutes();
            efFranjaActual = resp.tipo_estadia === 'noches'
                ? efDetectarFranja(horaMin)
                : 'horas';

            const ahoraMin = new Date();
            ahoraMin.setSeconds(0, 0);
            document.getElementById('efFechaEntrada').min = toLocalDateTimeString(ahoraMin);
            efRecalcular();

            document.getElementById('efCargando').style.display   = 'none';
            document.getElementById('efContenido').style.display  = 'block';
            document.getElementById('efBtnGuardar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando editar-fechas-info:', err);
            document.getElementById('efCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la información.';
        });
};

function efDetectarFranja(horaEnMinutos) {
    if (horaEnMinutos >= 0 && horaEnMinutos <= EF_MADRUGADA_FIN) return 'madrugada';
    if (horaEnMinutos >= EF_EARLY_INICIO && horaEnMinutos <= EF_EARLY_FIN) return 'early';
    if (horaEnMinutos > EF_EARLY_FIN && horaEnMinutos < EF_CHECKIN_NORMAL) return 'intermedio';
    return 'normal';
}

function efTipoNombre() {
    return document.getElementById('efTipoEstadiaId').value;
}

window.efOnTipoChange = function () {
    document.getElementById('efFechaEntrada').value = '';
    document.getElementById('efFechaSalida').value  = '';
    efFranjaActual = '';
    document.getElementById('efAvisoFranja').style.display = 'none';
    document.getElementById('efResumen').style.display     = 'none';
};

// Redondea, detecta franja y sugiere salida al cambiar la entrada
window.efOnEntradaChange = function () {
    const tipo    = efTipoNombre();
    const entrada = document.getElementById('efFechaEntrada').value;
    if (!tipo || !entrada) return;

    const ahora = new Date();
    ahora.setSeconds(0, 0);
    let entradaDt = new Date(entrada);
    if (entradaDt < ahora) {
        entradaDt = ahora;
    }

    const mins = entradaDt.getMinutes();
    if (mins % 10 !== 0) {
        const redondeado = Math.ceil(mins / 10) * 10;
        if (redondeado === 60) {
            entradaDt.setHours(entradaDt.getHours() + 1, 0, 0, 0);
        } else {
            entradaDt.setMinutes(redondeado, 0, 0);
        }
    }

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
    efVerificarDisponibilidad(); 
};

// Fuerza 11:00 AM si es por noches al cambiar la salida
window.efOnSalidaChange = function () {
    const tipo    = efTipoNombre();
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;

    if (!tipo || !entrada || !salida) return;

    if (tipo === 'noches') {
        const dt = new Date(salida);
        dt.setHours(11, 0, 0, 0);
        document.getElementById('efFechaSalida').value = toLocalDateTimeString(dt);
    }

    efMostrarAviso();
    efRecalcular();
    efVerificarDisponibilidad(); 
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

    if (!tipo || !entrada || !salida) { ocultarAviso(aviso); return; }

    const entradaDt = new Date(entrada);
    const salidaDt  = new Date(salida);

    if (tipo === 'horas') {
        const horas = Math.round((salidaDt - entradaDt) / 3600000);
        pintarAviso(aviso, 'info', `Estadía por horas — Se cobrarán ${horas === 1 ? '1 hora' : horas + ' horas'}.`);
        return;
    }

    const entDia = new Date(entradaDt.getFullYear(), entradaDt.getMonth(), entradaDt.getDate());
    const salDia = new Date(salidaDt.getFullYear(), salidaDt.getMonth(), salidaDt.getDate());
    const diff   = Math.round((salDia - entDia) / 86400000);
    const noches = efFranjaActual === 'madrugada'
        ? (diff === 0 ? 1 : diff + 1)
        : (diff < 1 ? 1 : diff);
    const nText  = noches === 1 ? '1 noche' : `${noches} noches`;

    const msgs = {
        madrugada:  { cat: 'info',        texto: `Ingreso en madrugada — Se cobra ${nText}. Check out: 11:00 AM.` },
        early:      { cat: 'advertencia', texto: `Ingreso temprano — Se cobra ${nText} + recargo 2 horas. Check out: 11:00 AM.` },
        intermedio: { cat: 'exito',       texto: `Ingreso intermedio — Se cobra ${nText} sin recargo. Check out: 11:00 AM.` },
        normal:     { cat: 'info',        texto: `Se cobra ${nText}. Check out: 11:00 AM.` },
    };

    const msg = msgs[efFranjaActual];
    if (msg) {
        pintarAviso(aviso, msg.cat, msg.texto);
    } else {
        ocultarAviso(aviso);
    }
}

function efVerificarDisponibilidad() {
    const tipo    = document.getElementById('efTipoEstadiaId').value;
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;
    if (!tipo || !entrada || !salida) return;

    clearTimeout(efDebounceTimer);
    efDebounceTimer = setTimeout(() => {
        const params = new URLSearchParams({ fecha_entrada: entrada, fecha_salida: salida });

        fetch(`/reservas/${efReservaId}/editar-fechas-disponibilidad?${params}`, {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        })
            .then(res => res.json())
            .then(resp => {
                const aviso = document.getElementById('efAvisoConflicto');
                efConflictoActual = !resp.disponible;

                if (!resp.disponible) {
                    const habs = resp.conflictos.map(n => `N°${n}`).join(', ');
                    pintarAviso(aviso, 'peligro',
                        `<strong>Sin disponibilidad</strong><br>La(s) habitación(es) ${habs} ya no está(n) disponible(s) en este rango. Use "Reasignar Habitaciones" o elija otras fechas.`);
                } else {
                    ocultarAviso(aviso);
                }

                document.getElementById('efAvisoFranja').style.display      = efConflictoActual ? 'none' : 'block';
                document.getElementById('efObservacionGrupo').style.display = efConflictoActual ? 'none' : 'block';
                document.getElementById('efResumen').style.display          = efConflictoActual ? 'none' : 'block';
                document.getElementById('efBtnGuardar').style.display       = efConflictoActual ? 'none' : 'inline-flex';
            })
            .catch(() => {});
    }, 400);
}

// Recalcula el nuevo total y determina el caso (A/B/C/D) según diferencia con lo pagado
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
    let nuevoTotal      = 0;
    let nuevoMontoEarly = 0;

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
                const recargoHab = h.precio_hora_raw * 2;
                nuevoTotal      += recargoHab;
                nuevoMontoEarly += recargoHab;
            }
        }
    });

    efNuevoTotalCalc = Math.round(nuevoTotal * 100) / 100;
    const diferencia = Math.round((efNuevoTotalCalc - efMontoPagado) * 100) / 100;
    const minimo50   = Math.round(efNuevoTotalCalc * 0.5 * 100) / 100;

    const diferenciaRecargo = Math.max(0, Math.round((nuevoMontoEarly - efRecargoPagado) * 100) / 100);
    const faltaParaMinimo   = Math.max(0, Math.round((minimo50 - efMontoPagado) * 100) / 100);
    const minimoRequerido   = Math.max(faltaParaMinimo, diferenciaRecargo);

    document.getElementById('efNuevoTotal').textContent  = `S/ ${efNuevoTotalCalc.toFixed(2)}`;
    document.getElementById('efMontoPagado').textContent = `S/ ${efMontoPagado.toFixed(2)}`;

    const saldoEl   = document.getElementById('efSaldo');
    const labelEl   = document.getElementById('efSaldoLabel');
    const avisoEl   = document.getElementById('efAvisoCaso');
    const pagoGrupo = document.getElementById('efPagoGrupo');

    // Caso A: nuevo total mayor y falta cubrir mínimo (50% base y/o recargo nuevo)
    if (efNuevoTotalCalc > efMontoPagado && minimoRequerido > 0) {
        document.getElementById('efCreditoGrupo').style.display = 'none';
        labelEl.textContent = 'Saldo pendiente';
        saldoEl.textContent = `S/ ${diferencia.toFixed(2)}`;
        saldoEl.classList.add('ver-saldo-positivo');
        saldoEl.classList.remove('ver-saldo-cero');

        pintarAviso(avisoEl, 'advertencia', `
            El nuevo total requiere un pago adicional mínimo de <strong>S/ ${minimoRequerido.toFixed(2)}</strong>.
            ${diferenciaRecargo > 0 ? `<br><small>Incluye S/ ${diferenciaRecargo.toFixed(2)} de recargo por ingreso temprano.</small>` : ''}`);
        document.getElementById('efPagoMinimoLabel').textContent =
            `(mínimo S/ ${minimoRequerido.toFixed(2)}, máximo S/ ${diferencia.toFixed(2)})`;
        document.getElementById('efPagoMonto').min   = minimoRequerido;
        document.getElementById('efPagoMonto').max   = diferencia;
        document.getElementById('efPagoMonto').value = minimoRequerido.toFixed(2);
        document.getElementById('efPagoError').style.display = 'none';
        pagoGrupo.style.display = 'block';

    // Caso B: nuevo total mayor, ya cubre el mínimo requerido
    } else if (efNuevoTotalCalc > efMontoPagado && minimoRequerido <= 0) {
        document.getElementById('efCreditoGrupo').style.display = 'none';
        labelEl.textContent = 'Saldo pendiente';
        saldoEl.textContent = `S/ ${diferencia.toFixed(2)}`;
        saldoEl.classList.add('ver-saldo-positivo');
        saldoEl.classList.remove('ver-saldo-cero');

        pintarAviso(avisoEl, 'info',
            'El pago cubre el mínimo requerido. Se guardará con saldo pendiente para el check-in.');
        pagoGrupo.style.display = 'none';

    // Caso C: nuevo total menor que lo pagado (crédito a favor)
    } else if (efNuevoTotalCalc < efMontoPagado) {
        const credito = Math.round((efMontoPagado - efNuevoTotalCalc) * 100) / 100;
        labelEl.textContent = 'Crédito a favor';
        saldoEl.textContent = `S/ ${credito.toFixed(2)}`;
        saldoEl.classList.add('ver-saldo-cero');
        saldoEl.classList.remove('ver-saldo-positivo');

        pintarAviso(avisoEl, 'advertencia',
            'El nuevo total es menor a lo pagado. Decida cuánto devolver al huésped.');
        pagoGrupo.style.display = 'none';

        efCreditoTotal = credito;
        document.getElementById('efCreditoMaximoLabel').textContent = `(máximo S/ ${credito.toFixed(2)})`;
        document.getElementById('efCreditoMontoDevuelto').max   = credito;
        document.getElementById('efCreditoMontoDevuelto').value = credito.toFixed(2);
        document.getElementById('efCreditoError').style.display = 'none';
        document.getElementById('efCreditoGrupo').style.display = 'block';
        window.efActualizarInfoRetenido();

    // Caso D: el total no cambia
    } else {
        document.getElementById('efCreditoGrupo').style.display = 'none';
        labelEl.textContent = 'Saldo pendiente';
        saldoEl.textContent = 'S/ 0.00';
        saldoEl.classList.add('ver-saldo-cero');
        saldoEl.classList.remove('ver-saldo-positivo');

        pintarAviso(avisoEl, 'exito', 'El total no cambia. Se guardará sin modificar los pagos.');
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

// Muestra cuánto queda retenido según lo que se decide devolver
window.efActualizarInfoRetenido = function () {
    const devuelto  = parseFloat(document.getElementById('efCreditoMontoDevuelto').value) || 0;
    const infoEl    = document.getElementById('efCreditoRetenidoInfo');
    const metodoGrp = document.getElementById('efCreditoMetodoGrupo');

    if (devuelto < 0 || devuelto > efCreditoTotal) {
        ocultarAviso(infoEl);
        metodoGrp.style.display = 'none';
        return;
    }

    const retenido = Math.round((efCreditoTotal - devuelto) * 100) / 100;

    if (devuelto <= 0) {
        pintarAviso(infoEl, 'advertencia', `No se devolverá nada. Se retiene el total: S/ ${efCreditoTotal.toFixed(2)}.`);
        metodoGrp.style.display = 'none';
    } else if (retenido <= 0.009) {
        pintarAviso(infoEl, 'exito', 'Se devolverá el crédito completo. Nada queda retenido.');
        metodoGrp.style.display = 'block';
    } else {
        pintarAviso(infoEl, 'info', `Se devuelve S/ ${devuelto.toFixed(2)} y se retiene S/ ${retenido.toFixed(2)}.`);
        metodoGrp.style.display = 'block';
    }
};

window.efValidarCredito = function () {
    const monto   = parseFloat(document.getElementById('efCreditoMontoDevuelto').value) || 0;
    const errorEl = document.getElementById('efCreditoError');

    if (monto < 0) {
        errorEl.textContent   = 'El monto no puede ser negativo.';
        errorEl.style.display = 'block';
    } else if (monto > efCreditoTotal) {
        errorEl.textContent   = `No puede superar el crédito de S/ ${efCreditoTotal.toFixed(2)}`;
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }

    window.efActualizarInfoRetenido();
};

function efCreditoMetodoEsEfectivo() {
    const select = document.getElementById('efCreditoMetodo');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.efOnCambioMetodoCredito = function () {
    const grupo = document.getElementById('efCreditoGrupoNumeroOperacion');
    const select = document.getElementById('checkinMetodoId');
    
    if (select.value !== '' && !efCreditoMetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('efCreditoNumeroOperacion').value = '';
        document.getElementById('efCreditoErrorNumeroOperacion').style.display = 'none';
    }
};

window.efValidarNumeroOperacionCredito = function () {
    const valor = document.getElementById('efCreditoNumeroOperacion').value.trim();
    document.getElementById('efCreditoErrorNumeroOperacion').style.display = valor ? 'none' : 'block';
};

// Guarda los cambios de fechas/tipo (y pago adicional/devolución si aplica)
window.efGuardar = function () {
    const tipo    = document.getElementById('efTipoEstadiaId').value;
    const entrada = document.getElementById('efFechaEntrada').value;
    const salida  = document.getElementById('efFechaSalida').value;
    const errorGeneralEl = document.getElementById('efErrorGeneral');

    if (!tipo || !entrada || !salida) {
        errorGeneralEl.style.display = 'block';
        return;
    }
    errorGeneralEl.style.display = 'none';

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
            document.getElementById('efErrorMetodo').style.display = 'block';
            return;
        }
        document.getElementById('efErrorMetodo').style.display = 'none';
        if (!efMetodoEsEfectivo()) {
            const numOp = document.getElementById('efNumeroOperacion').value.trim();
            if (!numOp) {
                document.getElementById('efErrorNumeroOperacion').style.display = 'block';
                return;
            }
        }
    }

    const creditoGrupoVisible = document.getElementById('efCreditoGrupo').style.display !== 'none';
    if (creditoGrupoVisible) {
        const devuelto = parseFloat(document.getElementById('efCreditoMontoDevuelto').value) || 0;

        if (devuelto < 0 || devuelto > efCreditoTotal) {
            document.getElementById('efCreditoError').style.display = 'block';
            return;
        }
        if (devuelto > 0 && !document.getElementById('efCreditoMetodo').value) {
            document.getElementById('efCreditoErrorMetodo').style.display = 'block';
            return;
        }
        if (devuelto > 0 && !efCreditoMetodoEsEfectivo()) {
            const numOpCredito = document.getElementById('efCreditoNumeroOperacion').value.trim();
            if (!numOpCredito) {
                document.getElementById('efCreditoErrorNumeroOperacion').style.display = 'block';
                return;
            }
        }
    }

    const btn = document.getElementById('efBtnGuardar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const payload = {
        tipo_estadia: tipo,
        fecha_entrada:   entrada,
        fecha_salida:    salida,
        observacion:     document.getElementById('efObservacion').value,
        franja:          efFranjaActual || 'normal',
    };

    if (pagoGrupoVisible) {
        payload.monto_pago = parseFloat(document.getElementById('efPagoMonto').value);
        payload.metodo_id  = document.getElementById('efPagoMetodo').value;
        if (!efMetodoEsEfectivo()) {
            payload.numero_operacion = document.getElementById('efNumeroOperacion').value.trim();
        }
    }

    if (creditoGrupoVisible) {
        payload.credito_monto_devuelto = parseFloat(document.getElementById('efCreditoMontoDevuelto').value) || 0;
        if (payload.credito_monto_devuelto > 0) {
            payload.credito_metodo_id = document.getElementById('efCreditoMetodo').value;
            if (!efCreditoMetodoEsEfectivo()) {
                payload.credito_numero_operacion = document.getElementById('efCreditoNumeroOperacion').value.trim();
            }
        }
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
        .then(async res => {
            let data = null;
            try { data = await res.json(); } catch (e) { data = null; }
            return { status: res.status, ok: res.ok, data };
        })
        .then(({ status, ok, data }) => {
            btn.disabled  = false;
            btn.innerHTML = 'Guardar Cambios';

            if (!ok) {
                let mensaje = 'Ocurrió un error al guardar los cambios.';
                if (data?.error) {
                    mensaje = data.error;
                } else if (data?.errors) {
                    mensaje = Object.values(data.errors).flat().join('\n');
                } else if (data?.message) {
                    mensaje = data.message;
                }
                console.error(`Editar fechas — status ${status}:`, data);
                mostrarAlerta('error', mensaje);
                return;
            }

            if (data?.error) {
                mostrarAlerta('error', data.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalEditarFechas')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Reserva actualizada correctamente.');
        })
        .catch(err => {
            console.error('Error guardando fechas (red/fetch):', err);
            mostrarAlerta('error', 'Ocurrió un error de conexión al guardar los cambios.');
            btn.disabled  = false;
            btn.innerHTML = 'Guardar Cambios';
        });
};

function efMetodoEsEfectivo() {
    const select = document.getElementById('efPagoMetodo');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.efOnCambioMetodo = function () {
    const grupo = document.getElementById('efGrupoNumeroOperacion');
    const select = document.getElementById('efPagoMetodo');

    if (select.value !== '' && !efMetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('efNumeroOperacion').value = '';
        document.getElementById('efErrorNumeroOperacion').style.display = 'none';
    }
};

window.efValidarNumeroOperacion = function () {
    const valor = document.getElementById('efNumeroOperacion').value.trim();
    document.getElementById('efErrorNumeroOperacion').style.display = valor ? 'none' : 'block';
};


// ── 14. ACCIÓN: REASIGNAR HABITACIONES (MODAL REASIGNAR) ──
let raReservaId          = null;
let raHabsData           = [];
let raCambios            = {};
let raActualSeleccionado = null;

// Abre el modal y carga las habitaciones actuales + sus alternativas
window.reasignarReserva = function (id) {
    cerrarTodosLosMenus();
    raReservaId          = id;
    raHabsData           = [];
    raCambios            = {};
    raActualSeleccionado = null;

    document.getElementById('raReservaId').textContent          = `#${id}`;
    document.getElementById('raCargando').style.display         = 'block';
    document.getElementById('raContenido').style.display        = 'none';
    document.getElementById('raBtnGuardar').style.display       = 'none';
    document.getElementById('raAvisoSinCambios').style.display   = 'none';
    document.getElementById('raMapaAlternativas').style.display  = 'none';
    document.getElementById('raSinAlternativas').style.display   = 'none';

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

            raHabsData.forEach(h => {
                raCambios[h.numero] = null;
            });

            raRenderizarActuales();

            document.getElementById('raCargando').style.display   = 'none';
            document.getElementById('raContenido').style.display  = 'block';
            document.getElementById('raBtnGuardar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando reasignar-info:', err);
            document.getElementById('raCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la información.';
        });
};

// Renderiza las tarjetas de habitaciones actuales (selector)
function raRenderizarActuales() {
    const contenedor = document.getElementById('raHabsActuales');
    contenedor.innerHTML = '';

    raHabsData.forEach(h => {
        const cambio = raCambios[h.numero];
        const activa = raActualSeleccionado === h.numero;
        const tieneCambio = cambio !== null;

        let altData = null;
        if (tieneCambio) {
            altData = h.alternativas?.find(a => a.numero === cambio) ?? null;
        }

        const par = document.createElement('div');
        par.className = 'ra-par';

        const tarjetaActual = document.createElement('div');

        const clases = ['tarjeta-habitacion'];
        if (activa) clases.push('tarjeta-seleccionada');
        if (tieneCambio) clases.push('ra-con-cambio');

        tarjetaActual.className = clases.join(' ');
        tarjetaActual.id        = `ra-actual-${h.numero}`;
        tarjetaActual.onclick   = () => window.raSeleccionarActual(h.numero);
        tarjetaActual.innerHTML = `
            <div class="tarjeta-numero">N° ${h.numero}</div>
            <div class="tarjeta-tipo">${h.tipo_nombre}</div>
            <div class="tarjeta-precio">S/ ${h.precio_aplicado}</div>
            ${tieneCambio ? `
                <button type="button" class="ra-btn-deshacer"
                    onclick="event.stopPropagation(); window.raDeshacerCambio(${h.numero})">
                    <i class="bi bi-x-lg"></i></button>` : ''}`;

        par.appendChild(tarjetaActual);

        if (tieneCambio && altData) {
            const flecha = document.createElement('div');
            flecha.className = 'ra-flecha';
            flecha.innerHTML = '<i class="bi bi-arrow-right"></i>';

            const tarjetaDestino = document.createElement('div');
            tarjetaDestino.className = 'tarjeta-habitacion ra-tarjeta-destino';
            tarjetaDestino.innerHTML = `
                <div class="tarjeta-numero">N° ${altData.numero}</div>
                <div class="tarjeta-tipo">${altData.tipo_nombre}</div>
                <div class="tarjeta-precio ra-precio-destino">Nuevo</div>`;

            par.appendChild(flecha);
            par.appendChild(tarjetaDestino);
        }

        contenedor.appendChild(par);
    });
}

window.raSeleccionarActual = function (numero) {
    raActualSeleccionado = numero;
    raRenderizarActuales();
    raRenderizarMapa(numero);
};

// Renderiza el mapa de alternativas por piso para la habitación seleccionada
function raRenderizarMapa(numeroActual) {
    const habActual = raHabsData.find(h => h.numero === numeroActual);

    const mapaWrap   = document.getElementById('raMapaAlternativas');
    const sinAlt     = document.getElementById('raSinAlternativas');
    const contenedor = document.getElementById('raMapaContenedor');

    if (!habActual.alternativas || habActual.alternativas.length === 0) {
        mapaWrap.style.display = 'none';
        sinAlt.style.display   = 'block';
        return;
    }

    sinAlt.style.display   = 'none';
    mapaWrap.style.display = 'block';
    contenedor.innerHTML   = '';

    const yaAsignados = new Set(Object.values(raCambios).filter(v => v !== null));

    const pisos = {};
    habActual.alternativas.forEach(alt => {
        if (yaAsignados.has(alt.numero)) return;
        const piso = Math.floor(alt.numero / 100);
        if (!pisos[piso]) pisos[piso] = [];
        pisos[piso].push(alt);
    });

    const seleccionActual = raCambios[numeroActual];

    Object.keys(pisos).sort((a, b) => a - b).forEach(piso => {
        const seccion = document.createElement('div');
        seccion.className = 'piso-seccion';

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

window.raToggleAlternativa = function (numeroActual, numeroAlt) {
    raCambios[numeroActual] = (raCambios[numeroActual] === numeroAlt) ? null : numeroAlt;

    raRenderizarActuales();
    raRenderizarMapa(numeroActual);
    document.getElementById('raAvisoSinCambios').style.display = 'none';
};

window.raDeshacerCambio = function (numero) {
    raCambios[numero] = null;
    raRenderizarActuales();
    if (raActualSeleccionado === numero) {
        raRenderizarMapa(numero);
    }
    document.getElementById('raAvisoSinCambios').style.display = 'none';
};

// Envía los cambios de reasignación al servidor
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
            btn.innerHTML = 'Guardar Cambios';

            if (resp.error) {
                mostrarAlerta('error', resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalReasignar')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Habitaciones reasignadas correctamente.');
        })
        .catch(err => {
            console.error('Error reasignando:', err);
            mostrarAlerta('error', 'Ocurrió un error al reasignar las habitaciones.');
            btn.disabled  = false;
            btn.innerHTML = 'Guardar Cambios';
        });
};


// ── 15. ACCIÓN: EDITAR HUÉSPEDES (MODAL HUÉSPEDES) ──
let huespedModalReservaId    = null;
let huespedModalActuales     = [];
let huespedModalMaxPermitido = 0;

// Abre el modal y carga los huéspedes actuales de la reserva
window.huespedesReserva = function (id) {
    cerrarTodosLosMenus();
    huespedModalReservaId = id;

    document.getElementById('huespedReservaId').textContent    = `#${id}`;
    document.getElementById('huespedCargando').style.display   = 'block';
    document.getElementById('huespedContenido').style.display  = 'none';
    document.getElementById('huespedBtnGuardar').style.display = 'none';
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

            huespedModalActuales = resp.huespedes.map(h => ({
                numDoc:    h.num_doc,
                nombre:    h.nombre,
                telefono:  h.telefono,
                principal: h.num_doc === resp.huesped_principal,
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

// Busca huéspedes por documento o nombre (mismo patrón que el Paso 1)
window.buscarHuespedModal = function () {
    const numDoc  = document.getElementById('huespedNumDoc').value.trim();
    const nombre  = document.getElementById('huespedNombre').value.trim();
    const errorEl = document.getElementById('huespedErrorBusqueda');

    const params = new URLSearchParams();
    if (numDoc) {
        params.set('num_doc', numDoc);
    } else if (nombre) {
        params.set('nombre', nombre);
    } else {
        errorEl.style.display = 'block';
        return;
    }
    errorEl.style.display = 'none';

    const btnBuscar = document.getElementById('huespedBtnBuscar');
    btnBuscar.disabled = true;
    const htmlOriginal = btnBuscar.innerHTML;
    btnBuscar.innerHTML = '<i class="bi bi-arrow-repeat"></i> Buscando...';

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
                const yaEsta = huespedModalActuales.some(s => s.numDoc === h.num_doc);
                const limiteAlcanzado = huespedModalActuales.length >= huespedModalMaxPermitido
                                        && huespedModalMaxPermitido > 0;
                const deshabilitado   = yaEsta || (limiteAlcanzado && !yaEsta);

                const item = document.createElement('div');
                item.className = 'paso3-item';
                item.innerHTML = `
                    <span class="huesped-nombre">${h.nombre}</span>
                    <span class="huesped-doc">${h.num_doc}</span>
                    <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                        ${h.telefono !== '—' ? h.telefono : '—'}
                    </span>
                    <button type="button"
                        class="btn-agregar-huesped ${yaEsta ? 'btn-agregar-ya' : ''} ${limiteAlcanzado && !yaEsta ? 'btn-agregar-limite' : ''}"
                        onclick="window.agregarHuespedModal('${escaparTexto(h.num_doc)}','${escaparTexto(h.nombre)}','${escaparTexto(h.telefono)}')"
                        ${deshabilitado ? 'disabled' : ''}>
                        <i class="bi bi-${yaEsta ? 'check-lg' : 'person-plus'}"></i>
                        ${yaEsta ? 'Agregado' : 'Agregar'}
                    </button>`;
                listaEl.appendChild(item);
            });
        })
        .finally(() => {
            btnBuscar.disabled  = false;
            btnBuscar.innerHTML = htmlOriginal;
        });
};

window.agregarHuespedModal = function (numDoc, nombre, telefono) {
    if (huespedModalActuales.some(h => h.numDoc === numDoc)) return;
    if (huespedModalMaxPermitido > 0 && huespedModalActuales.length >= huespedModalMaxPermitido) return;
    huespedModalActuales.push({ numDoc, nombre, telefono });
    renderizarSeleccionadosModal();

    document.getElementById('huespedListaResultados')
        .querySelectorAll('button').forEach(btn => {
            if (btn.getAttribute('onclick')?.includes(`agregarHuespedModal('${numDoc}',`)) {
                btn.disabled  = true;
                btn.classList.add('btn-agregar-ya');
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Agregado';
            }
        });
};

// Quita un huésped de la reserva (mínimo 1 obligatorio)
window.quitarHuespedModal = function (numDoc) {
    if (huespedModalActuales.length <= 1) {
        document.getElementById('huespedErrorGeneral').style.display = 'block';
        return;
    }

    const eraPrincipal = huespedModalActuales.find(h => h.numDoc === numDoc)?.principal;
    huespedModalActuales = huespedModalActuales.filter(h => h.numDoc !== numDoc);
    if (eraPrincipal && huespedModalActuales.length > 0) {
        huespedModalActuales[0].principal = true;
    }

    renderizarSeleccionadosModal();

    document.getElementById('huespedListaResultados')
        .querySelectorAll('button').forEach(btn => {
            const match = btn.getAttribute('onclick')?.match(/agregarHuespedModal\('([^']+)',/);
            if (match && match[1] === numDoc) {
                btn.disabled  = false;
                btn.classList.remove('btn-agregar-ya');
                btn.innerHTML = '<i class="bi bi-person-plus"></i> Agregar';
            }
        });
};

window.marcarPrincipalModal = function (numDoc) {
    huespedModalActuales.forEach(h => h.principal = (h.numDoc === numDoc));
    renderizarSeleccionadosModal();
};

window.limpiarBuscadorHuespedModal = function () {
    limpiarBuscadorHuespedModalInterno();
};

function limpiarBuscadorHuespedModalInterno() {
    document.getElementById('huespedNumDoc').value              = '';
    document.getElementById('huespedNombre').value              = '';
    document.getElementById('huespedListaResultados').innerHTML = '';
    document.getElementById('huespedResultados').style.display  = 'none';
    document.getElementById('huespedVacio').style.display       = 'none';
}

function actualizarContadorModal() {
    const total  = huespedModalActuales.length;
    const limite = huespedModalMaxPermitido;
    const badge  = document.getElementById('huespedContadorBadge');
    const aviso  = document.getElementById('huespedAvisoLimite');

    if (badge) {
        badge.textContent = limite > 0 ? `${total} / ${limite}` : total;
        badge.classList.toggle('badge-contador-limite', limite > 0 && total >= limite);
    }

    if (aviso) {
        if (limite > 0 && total >= limite) {
            pintarAviso(aviso, 'advertencia',
                `Límite alcanzado: máximo ${limite} huésped${limite !== 1 ? 'es' : ''} para las habitaciones de esta reserva.`);
        } else {
            ocultarAviso(aviso);
        }
    }
}

function renderizarSeleccionadosModal() {
    const contenedor = document.getElementById('huespedListaSeleccionados');
    const seccion    = document.getElementById('huespedSeleccionados');

    contenedor.innerHTML = '';
    actualizarContadorModal(); 

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
            <label class="huesped-principal-radio" title="Marcar como huésped principal">
                <input type="radio" name="huespedPrincipalModal"
                    ${h.principal ? 'checked' : ''}
                    onchange="window.marcarPrincipalModal('${h.numDoc}')">
            </label>
            <span class="huesped-nombre">${h.nombre}</span>
            <span class="huesped-doc">${h.numDoc}</span>
            <span class="${h.telefono !== '—' ? 'huesped-tel' : 'huesped-tel-vacio'}">
                ${h.telefono !== '—' ? h.telefono : '—'}
            </span>
            ${h.principal ? '<span class="badge-principal">Principal</span>' : ''}
            <button type="button"
                class="btn-quitar-huesped ${esElUltimo ? 'btn-quitar-disabled' : ''}"
                onclick="window.quitarHuespedModal('${h.numDoc}')"
                title="${esElUltimo ? 'Debe quedar al menos un huésped' : 'Quitar'}"
                ${esElUltimo ? 'disabled' : ''}>
                <i class="bi bi-x-lg"></i>
            </button>`;
        contenedor.appendChild(item);
    });
}

// Guarda la lista actualizada de huéspedes de la reserva
window.guardarHuespedes = function () {
    const errorEl = document.getElementById('huespedErrorGeneral');

    if (huespedModalActuales.length === 0) {
        errorEl.textContent   = 'La reserva debe tener al menos un huésped.';
        errorEl.style.display = 'block';
        return;
    }
    if (!huespedModalActuales.some(h => h.principal)) {
        errorEl.textContent   = 'Debe marcar un huésped como principal.';
        errorEl.style.display = 'block';
        return;
    }
    errorEl.style.display = 'none';

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
        body: JSON.stringify({
            huespedes: huespedModalActuales.map(h => h.numDoc),
            huesped_principal: huespedModalActuales.find(h => h.principal)?.numDoc,
        }),
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                mostrarAlerta('error', resp.error);
                btn.disabled  = false;
                btn.innerHTML = 'Guardar Cambios';
                return;
            }

            btn.disabled  = false;
            btn.innerHTML = 'Guardar Cambios';
            Modal.getInstance(document.getElementById('modalHuespedes')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Huéspedes actualizados correctamente.');
        })
        .catch(err => {
            console.error('Error guardando huéspedes:', err);
            mostrarAlerta('error', 'Ocurrió un error al guardar los huéspedes.');
            btn.disabled  = false;
            btn.innerHTML = 'Guardar Cambios';
        });
};


// ── 16. ACCIÓN: CHECK-IN (MODAL CHECKIN) ──
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

    document.getElementById('checkinNumeroOperacion').value              = '';
    document.getElementById('checkinGrupoNumeroOperacion').style.display = 'none';
    document.getElementById('checkinErrorNumeroOperacion').style.display = 'none';

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

            const habsEl = document.getElementById('checkinHabitaciones');
            habsEl.innerHTML = '';
            resp.habitaciones.forEach(h => {
                const div = document.createElement('div');
                div.className = 'ver-fila';

                if (h.disponible) {
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero}</span>
                        <span class="badge-estado badge-estado-disponible">Disponible</span>`;
                } else if (h.motivo === 'estado') {
                    const badgeClase = h.estado_actual === 'limpieza' ? 'badge-estado-limpieza' : 'badge-estado-mantenimiento';
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero}</span>
                        <span class="badge-estado ${badgeClase}">
                            <i class="bi bi-exclamation-triangle-fill"></i> ${h.estado_actual.charAt(0).toUpperCase() + h.estado_actual.slice(1)}
                        </span>`;
                } else {
                    div.innerHTML = `
                        <span class="ver-fila-label">N° ${h.numero}</span>
                        <span class="ver-fila-valor ver-fila-valor-alerta">
                            <i class="bi bi-clock"></i> Disponible a las ${h.disponible_a}
                        </span>`;
                }
                habsEl.appendChild(div);
            });

            const recargoEl = document.getElementById('checkinRecargo');
            const hayNoAptaPorEstado = resp.habitaciones.some(h => h.motivo === 'estado');

            if (!resp.todas_libres && hayNoAptaPorEstado) {
                pintarAviso(recargoEl, 'peligro',
                    '<strong>Habitación en limpieza/mantenimiento</strong><br>Debe marcarla como disponible desde el módulo de Habitaciones antes de hacer check-in.');
            } else if (!resp.todas_libres) {
                pintarAviso(recargoEl, 'peligro',
                    '<strong>Habitaciones no disponibles</strong><br>Una o más habitaciones no están libres aún. El check-in no puede realizarse en este momento.');
            } else if (resp.es_por_horas && resp.es_anticipada) {
                pintarAviso(recargoEl, 'exito',
                    `<strong>Ingreso anticipado</strong><br>La habitación está libre. Si hace check-in ahora, la salida quedará a las <strong>${resp.nueva_salida}</strong>. Sin costo adicional.`);
            } else if (resp.hay_recargo) {
                pintarAviso(recargoEl, 'advertencia',
                    `<strong>Ingreso anticipado — recargo aplicable</strong><br>Se cobrarán S/ ${resp.recargo.toFixed(2)} adicionales por 2 horas por habitación. Seleccione un método de pago para continuar.`);
                document.getElementById('checkinMetodoGrupo').style.display = 'block';
            } else {
                pintarAviso(recargoEl, 'exito',
                    '<strong>Habitaciones libres</strong><br>Sin recargo adicional. Puede proceder con el check-in.');
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

// Confirma el check-in, validando método de pago si hay recargo
window.confirmarCheckin = function () {
    const metodo = document.getElementById('checkinMetodoId').value;
    const btn    = document.getElementById('checkinBtnConfirmar');

    const recargoVisible = document.getElementById('checkinMetodoGrupo').style.display !== 'none';
    if (recargoVisible && !metodo) {
        document.getElementById('checkinErrorMetodo').style.display = 'block';
        return;
    }
    document.getElementById('checkinErrorMetodo').style.display = 'none';

    if (recargoVisible && !checkinMetodoEsEfectivo()) {
        const numOp = document.getElementById('checkinNumeroOperacion').value.trim();
        if (!numOp) {
            document.getElementById('checkinErrorNumeroOperacion').style.display = 'block';
            return;
        }
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Procesando...';

    const body = recargoVisible ? { metodo_id: metodo } : {};
    if (recargoVisible && !checkinMetodoEsEfectivo()) {
        body.numero_operacion = document.getElementById('checkinNumeroOperacion').value.trim();
    }

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
                mostrarAlerta('error', resp.error);
                btn.disabled  = false;
                btn.innerHTML = 'Confirmar Check-in';
                return;
            }
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Check-in';
            Modal.getInstance(document.getElementById('modalCheckin')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Check-in realizado correctamente.');
        })
        .catch(err => {
            console.error('Error checkin:', err);
            mostrarAlerta('error', 'Ocurrió un error al realizar el check-in.');
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Check-in';
        });
};

function checkinMetodoEsEfectivo() {
    const select = document.getElementById('checkinMetodoId');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.onCambioMetodoCheckin = function () {
    const grupo = document.getElementById('checkinGrupoNumeroOperacion');
    if (!checkinMetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('checkinNumeroOperacion').value = '';
        document.getElementById('checkinErrorNumeroOperacion').style.display = 'none';
    }
};

window.validarNumeroOperacionCheckin = function () {
    const valor = document.getElementById('checkinNumeroOperacion').value.trim();
    document.getElementById('checkinErrorNumeroOperacion').style.display = valor ? 'none' : 'block';
};


// ── 17. ACCIÓN: AGREGAR EXTENSIÓN (MODAL EXTENSIÓN) ──
let extReservaId       = null;
let extTipoEstadia     = null;
let extMontoCalculado  = 0;
let extHabsDisponibles = [];
let extHabsSeleccionadas = [];
let extHabitacionesData  = [];

// Abre el modal en Fase A y consulta el tipo de estadía de la reserva
window.extensionReserva = function (id) {
    cerrarTodosLosMenus();
    extReservaId = id;

    document.getElementById('extReservaId').textContent        = `#${id}`;
    document.getElementById('extCargando').style.display       = 'none';
    document.getElementById('extFaseA').style.display          = 'block';
    document.getElementById('extFaseB').style.display          = 'none';
    document.getElementById('extFaseC').style.display          = 'none';
    document.getElementById('extBtnConfirmar').style.display   = 'none';
    document.getElementById('extCantidad').value                = '1';
    document.getElementById('extMetodoId').value                = '';
    document.getElementById('extErrorMetodo').style.display     = 'none';
    document.getElementById('extAvisoConflicto').style.display  = 'none';
    document.getElementById('extHabitaciones').innerHTML        = '';
    document.getElementById('extNumeroOperacion').value               = '';
    document.getElementById('extGrupoNumeroOperacion').style.display  = 'none';
    document.getElementById('extErrorNumeroOperacion').style.display  = 'none';
    extMontoCalculado  = 0;
    extHabsDisponibles = [];
    extTipoEstadia     = null;

    const modal = new Modal(document.getElementById('modalExtension'));
    modal.show();

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
                    `<div class="aviso-franja franja-info"><i class="bi bi-exclamation-circle"></i> ${resp.error}</div>`;
                return;
            }
            extTipoEstadia = resp.tipo_estadia;
            const unidad   = extTipoEstadia === 'horas' ? 'horas' : 'noches';
            document.getElementById('extTipoLabel').textContent =
                `Estadía por ${unidad}`;
            document.getElementById('extCantidadLabel').textContent =
                `Cantidad de ${unidad} a extender`;
        })
        .catch(() => {});
};

window.extLimpiarResultado = function () {
    document.getElementById('extFaseB').style.display        = 'none';
    document.getElementById('extFaseC').style.display        = 'none';
    document.getElementById('extBtnConfirmar').style.display = 'none';
    extMontoCalculado    = 0;
    extHabsDisponibles   = [];
    extHabsSeleccionadas = [];
    extHabitacionesData  = [];
};

function extMetodoEsEfectivo() {
    const select = document.getElementById('extMetodoId');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.onCambioMetodoExtension = function () {
    const grupo = document.getElementById('extGrupoNumeroOperacion');
    const select = document.getElementById('extMetodoId');

    if (select.value !== '' && !extMetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('extNumeroOperacion').value = '';
        document.getElementById('extErrorNumeroOperacion').style.display = 'none';
    }
};

window.validarNumeroOperacionExtension = function () {
    const valor = document.getElementById('extNumeroOperacion').value.trim();
    document.getElementById('extErrorNumeroOperacion').style.display = valor ? 'none' : 'block';
};

// Verifica disponibilidad y costo de la extensión (Fase A → B/C)
window.extVerificar = function () {
    const cantidad = parseInt(document.getElementById('extCantidad').value) || 0;
    const errorCantidadEl = document.getElementById('extErrorCantidad');

    if (cantidad < 1) {
        errorCantidadEl.style.display = 'block';
        return;
    }
    errorCantidadEl.style.display = 'none';

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
                mostrarAlerta('error', resp.error);
                return;
            }

            extTipoEstadia       = resp.tipo_estadia;
            extHabitacionesData  = resp.habitaciones;
            extHabsDisponibles   = resp.habitaciones.filter(h => h.disponible).map(h => h.numero);
            extHabsSeleccionadas = [...extHabsDisponibles];

            document.getElementById('extAccionesMasivas').style.display = extHabitacionesData.length > 1 ? 'flex' : 'none';

            document.getElementById('extInfoSalida').innerHTML =
                `<i class="bi bi-arrow-right-circle-fill"></i>
                 Extensión solicitada: <strong>+${resp.unidad_label}</strong>`;

            extRenderizarHabitaciones();

            const hayConflictos = resp.habitaciones.some(h => !h.disponible);
            const avisoEl = document.getElementById('extAvisoConflicto');
            if (hayConflictos && resp.hay_disponibles) {
                pintarAviso(avisoEl, 'advertencia',
                    'Algunas habitaciones tienen conflicto y no pueden extenderse. Elija cuáles de las disponibles desea extender.');
            } else if (hayConflictos && !resp.hay_disponibles) {
                pintarAviso(avisoEl, 'peligro',
                    'Todas las habitaciones tienen conflicto. No es posible extender esta reserva con esta cantidad.');
            } else {
                ocultarAviso(avisoEl);
            }

            document.getElementById('extFaseB').style.display = 'block';
            extActualizarMontoYBoton();
        })
        .catch(err => {
            console.error('Error verificando extensión:', err);
            document.getElementById('extCargando').style.display = 'none';
            mostrarAlerta('error', 'Ocurrió un error al verificar la disponibilidad.');
        });
};

// Renderiza las habitaciones con checkbox para las disponibles
function extRenderizarHabitaciones() {
    const habsEl = document.getElementById('extHabitaciones');
    habsEl.innerHTML = '';

    extHabitacionesData.forEach(h => {
        const div = document.createElement('div');
        div.className = 'ver-fila';

        if (h.disponible) {
            const marcada = extHabsSeleccionadas.includes(h.numero);
            div.innerHTML = `
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; flex:1;">
                    <input type="checkbox" ${marcada ? 'checked' : ''}
                        onchange="window.extToggleHabitacion(${h.numero})">
                    <span class="ver-fila-label">N° ${h.numero} <span class="ver-tag">${h.tipo}</span></span>
                </label>
                <span class="ver-fila-valor ext-fila-disponible">
                    <span class="ext-texto-exito">
                        <i class="bi bi-check-circle-fill"></i> ${h.salida_actual} → ${h.nueva_salida}
                    </span>
                    <strong>S/ ${h.monto.toFixed(2)}</strong>
                </span>`;
        } else {
            const msgConflicto = h.estado_conflicto === 'activa'
                ? `Ocupada por reserva #${h.reserva_id} (activa)`
                : `Reservada por reserva #${h.reserva_id} (pendiente)`;
            div.innerHTML = `
                <span class="ver-fila-label">N° ${h.numero} <span class="ver-tag">${h.tipo}</span></span>
                <span class="ver-fila-valor ext-texto-conflicto">
                    <i class="bi bi-x-circle-fill"></i> ${msgConflicto}
                </span>`;
        }

        habsEl.appendChild(div);
    });
}

window.extToggleHabitacion = function (numero) {
    const idx = extHabsSeleccionadas.indexOf(numero);
    if (idx === -1) {
        extHabsSeleccionadas.push(numero);
    } else {
        extHabsSeleccionadas.splice(idx, 1);
    }
    extActualizarMontoYBoton();
};

window.extSeleccionarTodas = function (marcar) {
    extHabsSeleccionadas = marcar ? [...extHabsDisponibles] : [];
    extRenderizarHabitaciones();
    extActualizarMontoYBoton();
};

function extActualizarMontoYBoton() {
    const avisoSinSeleccion = document.getElementById('extAvisoSinSeleccion');

    if (extHabsSeleccionadas.length === 0) {
        document.getElementById('extFaseC').style.display        = 'none';
        document.getElementById('extBtnConfirmar').style.display = 'none';
        if (avisoSinSeleccion) {
            avisoSinSeleccion.style.display = extHabsDisponibles.length > 0 ? 'block' : 'none';
        }
        return;
    }
    if (avisoSinSeleccion) avisoSinSeleccion.style.display = 'none';

    const seleccionadas = extHabitacionesData.filter(h => extHabsSeleccionadas.includes(h.numero));
    extMontoCalculado = Math.round(seleccionadas.reduce((sum, h) => sum + h.monto, 0) * 100) / 100;

    document.getElementById('extMontoTotal').textContent = `S/ ${extMontoCalculado.toFixed(2)}`;
    document.getElementById('extFaseC').style.display        = 'block';
    document.getElementById('extBtnConfirmar').style.display = 'inline-flex';
}

// Confirma y guarda la extensión (solo habitaciones disponibles)
window.extConfirmar = function () {
    const metodo   = document.getElementById('extMetodoId').value;
    const cantidad = parseInt(document.getElementById('extCantidad').value) || 0;

    if (!metodo) {
        document.getElementById('extErrorMetodo').style.display = 'block';
        return;
    }
    document.getElementById('extErrorMetodo').style.display = 'none';

    if (extHabsSeleccionadas.length === 0) {
        return;
    }

    if (!extMetodoEsEfectivo()) {
        const numOp = document.getElementById('extNumeroOperacion').value.trim();
        if (!numOp) {
            document.getElementById('extErrorNumeroOperacion').style.display = 'block';
            return;
        }
    }

    const btn = document.getElementById('extBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const body = {
        cantidad:     cantidad,
        metodo_id:    metodo,
        habitaciones: extHabsSeleccionadas,
        monto:        extMontoCalculado,
    };
    if (!extMetodoEsEfectivo()) {
        body.numero_operacion = document.getElementById('extNumeroOperacion').value.trim();
    }

    fetch(`/reservas/${extReservaId}/extension`, {
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
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Extensión';

            if (resp.error) {
                mostrarAlerta('error', resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalExtension')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Extensión registrada correctamente.');
        })
        .catch(err => {
            console.error('Error confirmando extensión:', err);
            mostrarAlerta('error', 'Ocurrió un error al confirmar la extensión.');
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Extensión';
        });
};


// ── 18. ACCIÓN: FINALIZAR / CHECK-OUT (MODAL FINALIZAR) ──
let finReservaId        = null;
let finEstadosHabs      = {};
let finTipoComprobante  = null;
let finHuespedPrincipal = null;

// Abre el modal y carga las habitaciones de la reserva
window.finalizarReserva = function (id) {
    cerrarTodosLosMenus();
    finReservaId        = id;
    finEstadosHabs      = {};
    finTipoComprobante  = null;
    finHuespedPrincipal = null;

    document.getElementById('finReservaId').textContent         = `#${id}`;
    document.getElementById('finCargando').style.display        = 'block';
    document.getElementById('finContenido').style.display       = 'none';
    document.getElementById('finBtnConfirmar').style.display    = 'none';
    document.getElementById('finAvisoIncompleto').style.display = 'none';

    document.getElementById('finBtnBoleta').classList.remove('btn-comprobante-activo');
    document.getElementById('finBtnFactura').classList.remove('btn-comprobante-activo');
    document.getElementById('finInfoBoleta').style.display       = 'none';
    document.getElementById('finGrupoFactura').style.display     = 'none';
    document.getElementById('finRuc').value                      = '';
    document.getElementById('finRazonSocial').value               = '';
    document.getElementById('finErrorRuc').style.display          = 'none';
    document.getElementById('finErrorRazonSocial').style.display  = 'none';
    document.getElementById('finErrorComprobante').style.display  = 'none';

    const modal = new Modal(document.getElementById('modalFinalizar'));
    modal.show();

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

            resp.habitaciones.forEach(h => {
                finEstadosHabs[h.numero] = null;
            });

            finHuespedPrincipal = resp.huespedes.find(h => h.num_doc === resp.huesped_principal) ?? null;

            finRenderizarHabs(resp.habitaciones);

            document.getElementById('finCargando').style.display     = 'none';
            document.getElementById('finContenido').style.display    = 'block';
            document.getElementById('finBtnConfirmar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando reserva para finalizar:', err);
            document.getElementById('finCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la reserva.';
        });
};

// Renderiza una fila por habitación con sus dos botones de destino
function finRenderizarHabs(habitaciones) {
    const contenedor = document.getElementById('finHabitaciones');
    contenedor.innerHTML = '';

    habitaciones.forEach(h => {
        const div = document.createElement('div');
        div.className = 'ver-fila fin-fila';
        div.id         = `fin-fila-${h.numero}`;
        div.innerHTML = `
            <span class="ver-fila-label fin-fila-label">
                N° ${h.numero}
                <span class="ver-tag">${h.tipo}</span>
            </span>
            <div class="fin-fila-botones">
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

    const btnLimpieza      = document.getElementById(`fin-btn-limpieza-${numero}`);
    const btnMantenimiento = document.getElementById(`fin-btn-mantenimiento-${numero}`);

    if (estado === 'limpieza') {
        btnLimpieza.classList.add('btn-estado-activo-limpieza');
        btnMantenimiento.classList.remove('btn-estado-activo-mantenimiento');
    } else {
        btnMantenimiento.classList.add('btn-estado-activo-mantenimiento');
        btnLimpieza.classList.remove('btn-estado-activo-limpieza');
    }

    document.getElementById('finAvisoIncompleto').style.display = 'none';
};

window.finAplicarTodas = function (estado) {
    Object.keys(finEstadosHabs).forEach(numero => {
        window.finSeleccionar(parseInt(numero), estado);
    });
};

// Selecciona el tipo de comprobante y muestra el bloque correspondiente
window.finSeleccionarComprobante = function (tipo) {
    finTipoComprobante = tipo;

    const btnBoleta    = document.getElementById('finBtnBoleta');
    const btnFactura   = document.getElementById('finBtnFactura');
    const infoBoleta   = document.getElementById('finInfoBoleta');
    const grupoFactura = document.getElementById('finGrupoFactura');

    btnBoleta.classList.toggle('btn-comprobante-activo', tipo === 'boleta');
    btnFactura.classList.toggle('btn-comprobante-activo', tipo === 'factura');

    if (tipo === 'boleta') {
        const nombre = finHuespedPrincipal?.nombre ?? '—';
        const doc    = finHuespedPrincipal?.num_doc ?? '—';
        infoBoleta.innerHTML = `<i class="bi bi-person-check"></i> Se emitirá a: <strong>${nombre}</strong> (${doc})`;
        infoBoleta.style.display   = 'block';
        grupoFactura.style.display = 'none';
    } else {
        infoBoleta.style.display   = 'none';
        grupoFactura.style.display = 'block';
    }

    document.getElementById('finErrorComprobante').style.display = 'none';
};

window.finValidarRuc = function () {
    const valor   = document.getElementById('finRuc').value.trim();
    const errorEl = document.getElementById('finErrorRuc');
    const valido  = /^\d{11}$/.test(valor);
    errorEl.style.display = (valor.length > 0 && !valido) ? 'block' : 'none';
};

window.finValidarRazonSocial = function () {
    const valor = document.getElementById('finRazonSocial').value.trim();
    document.getElementById('finErrorRazonSocial').style.display = valor ? 'none' : 'block';
};

// Valida habitaciones + comprobante y envía el check-out
window.finConfirmar = function () {
    const incompletas = Object.values(finEstadosHabs).some(v => v === null);
    if (incompletas) {
        document.getElementById('finAvisoIncompleto').style.display = 'block';
        return;
    }
    document.getElementById('finAvisoIncompleto').style.display = 'none';

    if (!finTipoComprobante) {
        document.getElementById('finErrorComprobante').style.display = 'block';
        return;
    }
    document.getElementById('finErrorComprobante').style.display = 'none';

    let ruc = null;
    let razonSocial = null;

    if (finTipoComprobante === 'factura') {
        ruc         = document.getElementById('finRuc').value.trim();
        razonSocial = document.getElementById('finRazonSocial').value.trim();

        if (!/^\d{11}$/.test(ruc)) {
            document.getElementById('finErrorRuc').style.display = 'block';
            return;
        }
        if (!razonSocial) {
            document.getElementById('finErrorRazonSocial').style.display = 'block';
            return;
        }
    }

    const habitaciones = Object.entries(finEstadosHabs).map(([numero, estado_destino]) => ({
        numero:         parseInt(numero),
        estado_destino,
    }));

    const btn = document.getElementById('finBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Guardando...';

    const body = {
        habitaciones,
        tipo_comprobante: finTipoComprobante,
    };
    if (finTipoComprobante === 'factura') {
        body.ruc          = ruc;
        body.razon_social = razonSocial;
    }

    fetch(`/reservas/${finReservaId}/finalizar`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Check-out';

            if (resp.error) {
                mostrarAlerta('error', resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalFinalizar')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Reserva finalizada correctamente.');

            if (resp.comprobante_url) {
                window.open(resp.comprobante_url, '_blank');
            }
        })
        .catch(err => {
            console.error('Error finalizando reserva:', err);
            mostrarAlerta('error', 'Ocurrió un error al finalizar la reserva.');
            btn.disabled  = false;
            btn.innerHTML = 'Confirmar Check-out';
        });
};


// ── 19. ACCIÓN: CANCELAR (MODAL CANCELAR) ──
let cancelarReservaId    = null;
let cancelarMontoPagado  = 0;

// Abre el modal y carga el saldo pagado de la reserva
window.cancelarReserva = function (id) {
    cerrarTodosLosMenus();
    cancelarReservaId = id;

    document.getElementById('cancelarReservaId').textContent      = `#${id}`;
    document.getElementById('cancelarCargando').style.display     = 'block';
    document.getElementById('cancelarContenido').style.display    = 'none';
    document.getElementById('cancelarBtnConfirmar').style.display = 'none';

    document.getElementById('cancelarMontoDevuelto').value              = '';
    document.getElementById('cancelarErrorMonto').style.display         = 'none';
    document.getElementById('cancelarRetenidoInfo').style.display       = 'none';
    document.getElementById('cancelarMetodoId').value                   = '';
    document.getElementById('cancelarMetodoGrupo').style.display        = 'none';
    document.getElementById('cancelarErrorMetodo').style.display        = 'none';
    document.getElementById('cancelarNumeroOperacion').value            = '';
    document.getElementById('cancelarGrupoNumeroOperacion').style.display = 'none';
    document.getElementById('cancelarErrorNumeroOperacion').style.display = 'none';

    const modal = new Modal(document.getElementById('modalCancelar'));
    modal.show();

    fetch(`/reservas/${id}/cancelar-info`, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.error) {
                document.getElementById('cancelarCargando').innerHTML =
                    `<i class="bi bi-exclamation-circle"></i> ${resp.error}`;
                return;
            }

            cancelarMontoPagado = resp.monto_pagado;

            document.getElementById('cancelarMontoTotal').textContent  = `S/ ${resp.monto_total.toFixed(2)}`;
            document.getElementById('cancelarMontoPagado').textContent = `S/ ${resp.monto_pagado.toFixed(2)}`;
            document.getElementById('cancelarMaximoLabel').textContent = `(máximo S/ ${resp.monto_pagado.toFixed(2)})`;

            document.getElementById('cancelarMontoDevuelto').max   = cancelarMontoPagado;
            document.getElementById('cancelarMontoDevuelto').value = cancelarMontoPagado.toFixed(2);

            window.cancelActualizarInfoRetenido();

            document.getElementById('cancelarCargando').style.display     = 'none';
            document.getElementById('cancelarContenido').style.display    = 'block';
            document.getElementById('cancelarBtnConfirmar').style.display = 'inline-flex';
        })
        .catch(err => {
            console.error('Error cargando cancelar-info:', err);
            document.getElementById('cancelarCargando').innerHTML =
                '<i class="bi bi-exclamation-circle"></i> Error al cargar la información.';
        });
};

window.cancelValidarMonto = function () {
    const monto   = parseFloat(document.getElementById('cancelarMontoDevuelto').value) || 0;
    const errorEl = document.getElementById('cancelarErrorMonto');

    if (monto < 0) {
        errorEl.textContent   = 'El monto no puede ser negativo.';
        errorEl.style.display = 'block';
    } else if (monto > cancelarMontoPagado) {
        errorEl.textContent   = `No puede superar lo pagado (S/ ${cancelarMontoPagado.toFixed(2)})`;
        errorEl.style.display = 'block';
    } else {
        errorEl.style.display = 'none';
    }

    window.cancelActualizarInfoRetenido();
};

// Muestra cuánto se devuelve y cuánto queda retenido
window.cancelActualizarInfoRetenido = function () {
    const devuelto  = parseFloat(document.getElementById('cancelarMontoDevuelto').value) || 0;
    const retenido  = Math.round((cancelarMontoPagado - devuelto) * 100) / 100;
    const infoEl    = document.getElementById('cancelarRetenidoInfo');
    const metodoGrp = document.getElementById('cancelarMetodoGrupo');

    if (devuelto <= 0) {
        pintarAviso(infoEl, 'advertencia', `No se devolverá nada. Se retiene el total pagado: S/ ${cancelarMontoPagado.toFixed(2)}.`);
        metodoGrp.style.display = 'none';
    } else if (retenido <= 0.009) {
        pintarAviso(infoEl, 'exito', 'Se devolverá todo lo pagado. Nada queda retenido.');
        metodoGrp.style.display = 'block';
    } else {
        pintarAviso(infoEl, 'info', `Se devuelve S/ ${devuelto.toFixed(2)} y se retiene S/ ${retenido.toFixed(2)}.`);
        metodoGrp.style.display = 'block';
    }

    const grupoNumOp = document.getElementById('cancelarGrupoNumeroOperacion');
    const metodoSeleccionado = document.getElementById('cancelarMetodoId').value;

    if (devuelto <= 0 || !metodoSeleccionado) {
        grupoNumOp.style.display = 'none';
    } else if (!cancelMetodoEsEfectivo()) {
        grupoNumOp.style.display = 'block';
    } else {
        grupoNumOp.style.display = 'none';
    }
};

function cancelMetodoEsEfectivo() {
    const select = document.getElementById('cancelarMetodoId');
    const opcion = select.options[select.selectedIndex];
    return (opcion?.dataset.nombre ?? '') === 'efectivo';
}

window.onCambioMetodoCancelar = function () {
    const grupo = document.getElementById('cancelarGrupoNumeroOperacion');
    const select = document.getElementById('cancelarMetodoId');

    if (select.value !== '' && !cancelMetodoEsEfectivo()) {
        grupo.style.display = 'block';
    } else {
        grupo.style.display = 'none';
        document.getElementById('cancelarNumeroOperacion').value = '';
        document.getElementById('cancelarErrorNumeroOperacion').style.display = 'none';
    }
};

window.validarNumeroOperacionCancelar = function () {
    const valor = document.getElementById('cancelarNumeroOperacion').value.trim();
    document.getElementById('cancelarErrorNumeroOperacion').style.display = valor ? 'none' : 'block';
};

// Confirma y procesa la cancelación (+ devolución si aplica)
window.confirmarCancelar = function () {
    const monto = parseFloat(document.getElementById('cancelarMontoDevuelto').value) || 0;

    if (monto < 0 || monto > cancelarMontoPagado + 0.009) {
        document.getElementById('cancelarErrorMonto').style.display = 'block';
        document.getElementById('cancelarErrorMonto').textContent   =
            `Ingrese un monto válido (máximo S/ ${cancelarMontoPagado.toFixed(2)})`;
        return;
    }

    let metodo = null;
    if (monto > 0) {
        metodo = document.getElementById('cancelarMetodoId').value;
         if (!metodo) {
            document.getElementById('cancelarErrorMetodo').style.display = 'block';
            return;
        }
        document.getElementById('cancelarErrorMetodo').style.display = 'none';

        if (!cancelMetodoEsEfectivo()) {
            const numOp = document.getElementById('cancelarNumeroOperacion').value.trim();
            if (!numOp) {
                document.getElementById('cancelarErrorNumeroOperacion').style.display = 'block';
                return;
            }
        }
    }

    const btn = document.getElementById('cancelarBtnConfirmar');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Cancelando...';

    const body = { monto_devuelto: monto };
    if (monto > 0) {
        body.metodo_id = metodo;
        if (!cancelMetodoEsEfectivo()) {
            body.numero_operacion = document.getElementById('cancelarNumeroOperacion').value.trim();
        }
    }

    fetch(`/reservas/${cancelarReservaId}/cancelar`, {
        method: 'PATCH',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    })
        .then(res => res.json())
        .then(resp => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Sí, cancelar reserva';

            if (resp.error) {
                mostrarAlerta('error', resp.error);
                return;
            }

            Modal.getInstance(document.getElementById('modalCancelar')).hide();
            window.buscarReservas(paginaActual);
            mostrarAlerta('exito', 'Reserva cancelada correctamente.');
        })
        .catch(err => {
            console.error('Error cancelando reserva:', err);
            mostrarAlerta('error', 'Ocurrió un error al cancelar la reserva.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Sí, cancelar reserva';
        });
};


// ── 20. INICIALIZACIÓN — listeners DOMContentLoaded ──
document.addEventListener('DOMContentLoaded', () => {

    // Modal Crear — limpiar todo el wizard al cerrar
    document.getElementById('modalCrear').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formCrear').reset();

        franjaDetectada          = '';
        ocultarAvisoFranja();

        habitacionesCargadasPara = '';
        pasoActual                = 1;
        document.getElementById('paso1').style.display = 'block';
        document.getElementById('paso2').style.display = 'none';
        document.getElementById('paso3').style.display = 'none';
        document.getElementById('paso4').style.display = 'none';
        document.getElementById('indicador1').classList.add('paso-activo');
        document.getElementById('indicador2').classList.remove('paso-activo');
        document.getElementById('indicador3').classList.remove('paso-activo');
        document.getElementById('indicador4').classList.remove('paso-activo');
        document.getElementById('btnAnterior').style.display   = 'none';
        document.getElementById('btnSiguiente').style.display  = 'inline-flex';
        document.getElementById('btnConfirmar').style.display  = 'none';

        habitacionesSeleccionadas = [];
        habitacionesData          = {};
        document.getElementById('paso2Mapa').style.display    = 'none';
        document.getElementById('paso2Resumen').style.display = 'none';
        ocultarAviso(document.getElementById('paso2AvisoCapacidad'));

        huespedesSeleccionados = [];
        maxHuespedesPermitido  = 0;
        window.limpiarBuscadorHuesped();
        document.getElementById('paso3Seleccionados').style.display = 'none';
        document.getElementById('paso3AvisoLimite').style.display   = 'none';
        document.getElementById('paso3ContadorBadge').textContent   = '';
        document.getElementById('paso3ContadorBadge').classList.remove('badge-contador-limite');

        document.getElementById('sugerenciaContenedor').style.display = 'none';
        document.getElementById('sugerenciaComentario').value = '';
        document.getElementById('sugerenciaInfo').style.display = 'none';
        document.getElementById('sugerenciaErrorComentario').style.display = 'none';
        document.getElementById('sugerenciaBtnGuardar').disabled = true;
        document.getElementById('sugerenciaBtnToggle').disabled  = true;

        paso4MontoTotal  = 0;
        paso4MontoMinimo = 0;
        paso4EsInmediata = false;
        document.getElementById('paso4MontoPago').value              = '';
        document.getElementById('paso4MetodoId').value               = '';
        document.getElementById('paso4FilasDesglose').innerHTML      = '';
        document.getElementById('paso4AvisoOcupacion').style.display = 'none';
        document.getElementById('paso4ErrorMonto').style.display     = 'none';
        document.getElementById('paso4NumeroOperacion').value        = '';
        document.getElementById('paso4GrupoNumeroOperacion').style.display = 'none';
        document.getElementById('paso4ErrorNumeroOperacion').style.display = 'none';

        // El backdrop de Bootstrap queda huérfano al resetear manualmente; se limpia aquí
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    });

    document.getElementById('modalCrear').addEventListener('show.bs.modal', function () {
        const ahora = new Date();
        ahora.setSeconds(0, 0);
        document.getElementById('fechaEntrada').min = toLocalDateTimeString(ahora);
    });

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

    ['paso3NumDoc', 'paso3Nombre'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); window.buscarHuesped(); }
        });
    });

    // Modal Pago: sin listener de cierre — pagoReserva() ya resetea todo al abrir

    // Modal Editar Fechas/Tipo — limpiar estado al cerrar
    document.getElementById('modalEditarFechas').addEventListener('hidden.bs.modal', function () {
        efReservaId      = null;
        efHabitaciones   = [];
        efMontoPagado    = 0;
        efRecargoPagado  = 0;
        efFranjaActual   = '';
        efNuevoTotalCalc = 0;
        efCreditoTotal = 0;
        efConflictoActual = false;
        document.getElementById('efFechaEntrada').value        = '';
        document.getElementById('efFechaSalida').value         = '';
        document.getElementById('efObservacion').value         = '';
        document.getElementById('efAvisoFranja').style.display = 'none';
        document.getElementById('efAvisoConflicto').style.display = 'none';
        document.getElementById('efResumen').style.display     = 'none';
        document.getElementById('efPagoGrupo').style.display   = 'none';
        document.getElementById('efAvisoCaso').style.display   = 'none';
        document.getElementById('efPagoMonto').value           = '';
        document.getElementById('efPagoMetodo').value          = '';
        document.getElementById('efPagoError').style.display   = 'none';
        document.getElementById('efNumeroOperacion').value     = '';
        document.getElementById('efGrupoNumeroOperacion').style.display = 'none';
        document.getElementById('efErrorNumeroOperacion').style.display = 'none';
        document.getElementById('efBtnGuardar').style.display  = 'none';
        document.getElementById('efCargando').style.display    = 'block';
        document.getElementById('efContenido').style.display   = 'none';
        document.getElementById('efCreditoGrupo').style.display = 'none';
        document.getElementById('efCreditoMontoDevuelto').value = '';
        document.getElementById('efCreditoError').style.display = 'none';
        document.getElementById('efCreditoMetodo').value        = '';
        document.getElementById('efCreditoErrorMetodo').style.display = 'none';
        document.getElementById('efCreditoNumeroOperacion').value               = '';
        document.getElementById('efCreditoGrupoNumeroOperacion').style.display  = 'none';
        document.getElementById('efCreditoErrorNumeroOperacion').style.display  = 'none';
    });

    // Modal Reasignar — limpiar estado al cerrar
    document.getElementById('modalReasignar').addEventListener('hidden.bs.modal', function () {
        raReservaId          = null;
        raHabsData           = [];
        raCambios            = {};
        raActualSeleccionado = null;
        document.getElementById('raHabsActuales').innerHTML          = '';
        document.getElementById('raMapaContenedor').innerHTML        = '';
        document.getElementById('raMapaAlternativas').style.display  = 'none';
        document.getElementById('raSinAlternativas').style.display   = 'none';
        document.getElementById('raAvisoSinCambios').style.display   = 'none';
        document.getElementById('raBtnGuardar').style.display        = 'none';
        document.getElementById('raCargando').style.display          = 'block';
        document.getElementById('raContenido').style.display         = 'none';
    });

    // Modal Editar Huéspedes — limpiar estado al cerrar
    document.getElementById('modalHuespedes').addEventListener('hidden.bs.modal', function () {
        huespedModalReservaId    = null;
        huespedModalActuales     = [];
        huespedModalMaxPermitido = 0;
        limpiarBuscadorHuespedModalInterno();
        document.getElementById('huespedListaSeleccionados').innerHTML = '';
        document.getElementById('huespedSeleccionados').style.display  = 'none';
        document.getElementById('huespedAvisoLimite').style.display    = 'none';
        document.getElementById('huespedContadorBadge').textContent    = '';
        document.getElementById('huespedContadorBadge').classList.remove('badge-contador-limite');
        document.getElementById('huespedBtnGuardar').style.display     = 'none';
        document.getElementById('huespedCargando').style.display       = 'block';
        document.getElementById('huespedContenido').style.display      = 'none';
    });

    ['huespedNumDoc', 'huespedNombre'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); window.buscarHuespedModal(); }
        });
    });

    // Modal Extensión — limpiar estado al cerrar
    document.getElementById('modalExtension').addEventListener('hidden.bs.modal', function () {
        extReservaId       = null;
        extTipoEstadia     = null;
        extMontoCalculado  = 0;
        extHabsDisponibles = [];
        extHabsSeleccionadas = [];
        extHabitacionesData  = [];
        document.getElementById('extCantidad').value                = '1';
        document.getElementById('extMetodoId').value                = '';
        document.getElementById('extErrorMetodo').style.display     = 'none';
        document.getElementById('extCargando').style.display        = 'none';
        document.getElementById('extFaseA').style.display           = 'block';
        document.getElementById('extFaseB').style.display           = 'none';
        document.getElementById('extFaseC').style.display           = 'none';
        document.getElementById('extBtnConfirmar').style.display    = 'none';
        document.getElementById('extHabitaciones').innerHTML        = '';
        document.getElementById('extAvisoConflicto').style.display  = 'none';
        document.getElementById('extAccionesMasivas').style.display = 'flex';
        document.getElementById('extInfoSalida').innerHTML          = '';
        document.getElementById('extTipoLabel').textContent         = '';
        document.getElementById('extNumeroOperacion').value               = '';
        document.getElementById('extGrupoNumeroOperacion').style.display  = 'none';
        document.getElementById('extErrorNumeroOperacion').style.display  = 'none';
    });

    // Modal Finalizar — limpiar estado al cerrar
    document.getElementById('modalFinalizar').addEventListener('hidden.bs.modal', function () {
        finReservaId   = null;
        finEstadosHabs = {};
        document.getElementById('finHabitaciones').innerHTML        = '';
        document.getElementById('finAvisoIncompleto').style.display = 'none';
        document.getElementById('finBtnConfirmar').style.display    = 'none';
        document.getElementById('finCargando').style.display        = 'block';
        document.getElementById('finContenido').style.display       = 'none';
        finTipoComprobante  = null;
        finHuespedPrincipal = null;
        document.getElementById('finBtnBoleta').classList.remove('btn-comprobante-activo');
        document.getElementById('finBtnFactura').classList.remove('btn-comprobante-activo');
        document.getElementById('finInfoBoleta').style.display       = 'none';
        document.getElementById('finGrupoFactura').style.display     = 'none';
        document.getElementById('finRuc').value                      = '';
        document.getElementById('finRazonSocial').value               = '';
        document.getElementById('finErrorRuc').style.display          = 'none';
        document.getElementById('finErrorRazonSocial').style.display  = 'none';
        document.getElementById('finErrorComprobante').style.display  = 'none';
    });

    // Modal Cancelar — limpiar estado al cerrar
    document.getElementById('modalCancelar').addEventListener('hidden.bs.modal', function () {
        cancelarReservaId   = null;
        cancelarMontoPagado = 0;
        document.getElementById('cancelarMontoDevuelto').value              = '';
        document.getElementById('cancelarErrorMonto').style.display         = 'none';
        document.getElementById('cancelarRetenidoInfo').style.display       = 'none';
        document.getElementById('cancelarMetodoId').value                   = '';
        document.getElementById('cancelarMetodoGrupo').style.display        = 'none';
        document.getElementById('cancelarErrorMetodo').style.display        = 'none';
        document.getElementById('cancelarNumeroOperacion').value            = '';
        document.getElementById('cancelarGrupoNumeroOperacion').style.display = 'none';
        document.getElementById('cancelarErrorNumeroOperacion').style.display = 'none';
        document.getElementById('cancelarBtnConfirmar').style.display       = 'none';
        document.getElementById('cancelarCargando').style.display           = 'block';
        document.getElementById('cancelarContenido').style.display          = 'none';
    });

    // Modal Ver — ocultar comprobante al cerrar (se repinta en cada apertura)
    document.getElementById('modalVer').addEventListener('hidden.bs.modal', function () {
        document.getElementById('verSeccionComprobante').style.display = 'none';
    });

    // Dropdown de acciones — cerrar al hacer click fuera o al hacer scroll
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.acciones-dropdown') && !e.target.closest('.acciones-menu')) {
            cerrarTodosLosMenus();
        }
    });
    window.addEventListener('scroll', cerrarTodosLosMenus, true);

    // Carga inicial de la tabla de reservas
    window.buscarReservas(1, true);
});
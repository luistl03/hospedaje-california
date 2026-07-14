<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Inicio\InicioController;
use App\Http\Controllers\Usuarios\UsuarioController;
use App\Http\Controllers\Habitaciones\HabitacionController;
use App\Http\Controllers\Habitaciones\TipoHabitacionController;
use App\Http\Controllers\Huespedes\HuespedController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Reportes\ReporteController;

require __DIR__.'/auth.php';

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/inicio', [InicioController::class, 'index'])->middleware('auth')->name('inicio');

// ============================================================
// ACCESIBLE PARA CUALQUIER USUARIO AUTENTICADO
// ============================================================
Route::middleware(['auth'])->group(function () {

    Route::post('/sistema/verificar-reservadas', [ReservaController::class, 'marcarHabitacionesReservadas'])
        ->name('sistema.verificarReservadas');

    // Habitaciones
    Route::get('/habitaciones', [HabitacionController::class, 'index'])->name('habitaciones.index');
    Route::get('/habitaciones/verificar-numero', [HabitacionController::class, 'verificarNumero'])->name('habitaciones.verificarNumero');
    Route::post('/habitaciones', [HabitacionController::class, 'store'])->name('habitaciones.store');
    Route::put('/habitaciones/{habitacion}', [HabitacionController::class, 'update'])->name('habitaciones.update');
    Route::get('/habitaciones/filtrar', [HabitacionController::class, 'filtrar'])->name('habitaciones.filtrar');

    // Huéspedes
    Route::get('/huespedes', [HuespedController::class, 'index'])->name('huespedes.index');
    Route::get('/huespedes/verificar-documento', [HuespedController::class, 'verificarDocumento'])->name('huespedes.verificarDocumento');
    Route::get('/huespedes/verificar-telefono', [HuespedController::class, 'verificarTelefono'])->name('huespedes.verificarTelefono');
    Route::get('/huespedes/filtrar', [HuespedController::class, 'filtrar'])->name('huespedes.filtrar');
    Route::post('/huespedes', [HuespedController::class, 'store'])->name('huespedes.store');
    Route::get('/huespedes/buscar', [HuespedController::class, 'buscar'])->name('huespedes.buscar');
    Route::put('/huespedes/{huesped}', [HuespedController::class, 'update'])->name('huespedes.update');
    Route::delete('/huespedes/{huesped}', [HuespedController::class, 'destroy'])->name('huespedes.destroy');

    // Reservas
    Route::get('/reservas', [ReservaController::class, 'index'])->name('reservas.index');
    Route::get('/reservas/filtrar', [ReservaController::class, 'filtrar'])->name('reservas.filtrar');
    Route::post('/reservas', [ReservaController::class, 'store'])->name('reservas.store');
    Route::get('/reservas/habitaciones-disponibles', [ReservaController::class, 'habitacionesDisponibles'])->name('reservas.habitacionesDisponibles');
    Route::get('/reservas/{reserva}', [ReservaController::class, 'show']);
    Route::post('/reservas/{reserva}/pago', [ReservaController::class, 'registrarPago'])->name('reservas.pago');
    Route::post('/reservas/{reserva}/extension', [ReservaController::class, 'agregarExtension'])->name('reservas.extension');
    Route::patch('/reservas/{reserva}/finalizar', [ReservaController::class, 'finalizar'])->name('reservas.finalizar');
    Route::patch('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar'])->name('reservas.cancelar');
    Route::get('/reservas/{reserva}/cancelar-info', [ReservaController::class, 'cancelarInfo'])->name('reservas.cancelarInfo');
    Route::get('/reservas/{reserva}/checkin-info', [ReservaController::class, 'checkinInfo'])->name('reservas.checkinInfo');
    Route::post('/reservas/{reserva}/checkin', [ReservaController::class, 'checkin'])->name('reservas.checkin');
    Route::get('/reservas/{reserva}/editar-fechas-info', [ReservaController::class, 'editarFechasInfo']);
    Route::get('/reservas/{reserva}/editar-fechas-disponibilidad', [ReservaController::class, 'editarFechasDisponibilidad']);
    Route::patch('/reservas/{reserva}/editar-fechas', [ReservaController::class, 'editarFechas']);
    Route::get('/reservas/{reserva}/reasignar-info', [ReservaController::class, 'reasignarInfo']);
    Route::patch('/reservas/{reserva}/reasignar', [ReservaController::class, 'reasignar']);
    Route::get('/reservas/{reserva}/huespedes-info',  [ReservaController::class, 'huespedInfo']);
    Route::patch('/reservas/{reserva}/huespedes',     [ReservaController::class, 'editarHuespedes']);
    Route::get('/reservas/{reserva}/extension-info', [ReservaController::class, 'extensionInfo'])->name('reservas.extensionInfo');
    Route::get('/reservas/{reserva}/comprobante', [ReservaController::class, 'comprobantePdf'])->name('reservas.comprobante');
});

// ============================================================
// EXCLUSIVO GERENTE
// ============================================================
Route::middleware(['auth', 'solo.gerente'])->group(function () {

    // Tipos de habitación
    Route::post('/tipos-habitacion', [TipoHabitacionController::class, 'store'])->name('tipos.store');
    Route::get('/tipos-habitacion/verificar-nombre', [TipoHabitacionController::class, 'verificarNombre'])->name('tipos.verificarNombre');
    Route::put('/tipos-habitacion/{tipoHabitacion}', [TipoHabitacionController::class, 'update'])->name('tipos.update');
    Route::delete('/tipos-habitacion/{tipoHabitacion}', [TipoHabitacionController::class, 'destroy'])->name('tipos.destroy');

    // Habitación
    Route::delete('/habitaciones/{habitacion}', [HabitacionController::class, 'destroy'])->name('habitaciones.destroy');

    // Usuarios
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/verificar-email', [UsuarioController::class, 'verificarEmail'])->name('usuarios.verificarEmail');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/datos', [ReporteController::class, 'datos'])->name('reportes.datos');
});
<?php

namespace App\Http\Controllers\Inicio;

use App\Http\Controllers\Controller;
use App\Models\Habitacion;
use App\Models\EstadoHabitacion;
use App\Models\Reserva;
use App\Models\EstadoReserva;
use Illuminate\Support\Carbon;

class InicioController extends Controller
{
    public function index()
    {
        $estadosHabId = EstadoHabitacion::pluck('nombre', 'id');

        $habitaciones = Habitacion::with('tipo')
            ->where('activo', 1)
            ->orderBy('numero')
            ->get();

        $idActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');

        $reservasVigentes = Reserva::with('habitaciones')
            ->whereIn('estado_id', [$idActiva, $idPendiente])
            ->get();

        $reservaPorHabitacion = [];
        foreach ($reservasVigentes as $reserva) {
            foreach ($reserva->habitaciones as $hab) {
                $reservaPorHabitacion[$hab->numero] = $reserva;
            }
        }

        $mapaPisos = $habitaciones->groupBy(fn($h) => intdiv($h->numero, 100))
            ->map(function ($habs, $piso) use ($reservaPorHabitacion, $estadosHabId) {
                return [
                    'piso' => $piso,
                    'habitaciones' => $habs->map(function ($h) use ($reservaPorHabitacion, $estadosHabId) {
                        $estado  = $estadosHabId[$h->estado_id] ?? 'desconocido';
                        $reserva = $reservaPorHabitacion[$h->numero] ?? null;

                        $mostrarHuesped = in_array($estado, ['reservada', 'ocupada']);

                        return [
                            'numero'       => $h->numero,
                            'tipo_nombre'  => $h->tipo->nombre,
                            'estado'       => $estado,
                            'huesped'      => $mostrarHuesped ? ($reserva->huesped_principal ?? null) : null,
                            'fecha_salida' => $mostrarHuesped ? $reserva?->fecha_salida?->format('d/m H:i') : null,
                        ];
                    })->values(),
                ];
            })->values();

        $conteoEstados = $habitaciones->groupBy(fn($h) => $estadosHabId[$h->estado_id] ?? 'desconocido')
            ->map->count();

        $estadosOrden = ['disponible', 'reservada', 'ocupada', 'limpieza', 'mantenimiento'];
        $indicadores  = collect($estadosOrden)->mapWithKeys(fn($e) => [$e => $conteoEstados[$e] ?? 0]);

        $hoy   = Carbon::today();
        $ahora = Carbon::now();

        $checkinsHoy = Reserva::with('habitaciones')
            ->where('estado_id', $idPendiente)
            ->whereDate('fecha_entrada', $hoy)
            ->orderBy('fecha_entrada')
            ->get()
            ->map(fn($r) => [
                'huesped'      => $r->huesped_principal,
                'hora'         => $r->fecha_entrada->format('H:i'),
                'habitaciones' => $r->habitaciones->pluck('numero')->join(', '),
                'atrasado'     => $r->fecha_entrada->lt($ahora),
                'saldo'        => (float) $r->saldo_pendiente,
            ]);

        $checkoutsHoy = Reserva::with('habitaciones')
            ->where('estado_id', $idActiva)
            ->whereDate('fecha_salida', $hoy)
            ->orderBy('fecha_salida')
            ->get()
            ->map(fn($r) => [
                'huesped'      => $r->huesped_principal,
                'hora'         => $r->fecha_salida->format('H:i'),
                'habitaciones' => $r->habitaciones->pluck('numero')->join(', '),
                'atrasado'     => $r->fecha_salida->lt($ahora),
            ]);

        $pagosPendientes = Reserva::with('habitaciones')
            ->where('estado_id', $idPendiente)
            ->where('saldo_pendiente', '>', 0)
            ->orderBy('fecha_entrada')
            ->get()
            ->map(fn($r) => [
                'huesped'       => $r->huesped_principal,
                'fecha_entrada' => $r->fecha_entrada->format('d/m H:i'),
                'saldo'         => (float) $r->saldo_pendiente,
                'habitaciones'  => $r->habitaciones->pluck('numero')->join(', '),
            ]);

        return view('inicio.index', compact(
            'mapaPisos', 'indicadores', 'checkinsHoy', 'checkoutsHoy', 'pagosPendientes'
        ));
    }
}
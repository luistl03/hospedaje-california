<?php

namespace App\Http\Controllers\Habitaciones;

use App\Http\Controllers\Controller;
use App\Models\EstadoHabitacion;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use App\Models\EstadoReserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HabitacionController extends Controller
{
    // true si la habitación tiene una reserva activa.
    private function tieneReservaActiva(int $numero): bool
    {
        $id = EstadoReserva::where('nombre', 'activa')->value('id');

        return DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->where('reserva_habitaciones.habitacion_numero', $numero)
            ->where('reservas.estado_id', $id)
            ->exists();
    }

    // true si la habitación tiene una reserva pendiente.
    private function tieneReservaPendiente(int $numero): bool
    {
        $id = EstadoReserva::where('nombre', 'pendiente')->value('id');

        return DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->where('reserva_habitaciones.habitacion_numero', $numero)
            ->where('reservas.estado_id', $id)
            ->exists();
    }

    // true si la habitación aparece en CUALQUIER reserva (incluye finalizadas/canceladas). Protege el historial contra borrado.
    private function tieneHistorialReservas(int $numero): bool
    {
        return DB::table('reserva_habitaciones')
            ->where('habitacion_numero', $numero)
            ->exists();
    }

    public function index()
    {
        $tipos         = TipoHabitacion::where('activo', 1)->get();
        $todosLosTipos = TipoHabitacion::all();
        $estados       = EstadoHabitacion::all();

        return view('habitaciones.index', compact('tipos', 'todosLosTipos', 'estados'));
    }

    public function verificarNumero(Request $request)
    {
        $existe = Habitacion::where('numero', $request->numero)
            ->where('numero', '!=', $request->numero_original ?? 0)
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'numero'  => 'required|integer|unique:habitaciones,numero',
            'tipo_id' => 'required|exists:tipos_habitacion,id',
        ], [
            'numero.required'  => 'El número de habitación es obligatorio.',
            'numero.unique'    => 'Este número de habitación ya existe.',
            'tipo_id.required' => 'El tipo de habitación es obligatorio.',
            'tipo_id.exists'   => 'El tipo seleccionado no existe.',
        ]);

        $estadoDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');

        Habitacion::create([
            'numero'    => $request->numero,
            'tipo_id'   => $request->tipo_id,
            'estado_id' => $estadoDisponible,
            'activo'    => 1,
        ]);

        return redirect()->route('habitaciones.index')
            ->with('exito', 'Habitación creada correctamente.');
    }

    public function update(Request $request, Habitacion $habitacion)
    {
        if ($this->tieneReservaActiva($habitacion->numero)) {
            return redirect()->route('habitaciones.index')
                ->with('error', 'No se puede editar: la habitación está ocupada (reserva activa).');
        }

        $tienePendiente = $this->tieneReservaPendiente($habitacion->numero);
        $estadoNuevo    = EstadoHabitacion::find($request->estado_id);

        if ($tienePendiente) {
            if (!$estadoNuevo || !in_array($estadoNuevo->nombre, ['disponible', 'limpieza', 'mantenimiento'])) {
                return redirect()->route('habitaciones.index')
                    ->with('error', 'Esta habitación tiene una reserva próxima: solo puede pasarla a Limpieza o Mantenimiento.');
            }

            if ((int) $request->numero !== (int) $habitacion->numero
                || (int) $request->tipo_id !== (int) $habitacion->tipo_id
                || (int) $request->activo !== 1) {
                return redirect()->route('habitaciones.index')
                    ->with('error', 'Esta habitación tiene una reserva próxima: no se puede cambiar número, tipo, ni desactivarla.');
            }
        } elseif ($estadoNuevo && in_array($estadoNuevo->nombre, ['ocupada', 'reservada'])) {
            return redirect()->route('habitaciones.index')
                ->with('error', 'El estado "' . $estadoNuevo->nombre . '" solo se asigna desde reservas.');
        }

        $request->validate([
            'numero'    => 'required|integer|unique:habitaciones,numero,' . $habitacion->numero . ',numero',
            'tipo_id'   => 'required|exists:tipos_habitacion,id',
            'estado_id' => 'required|exists:estados_habitacion,id',
            'activo'    => 'required|in:0,1',
        ], [
            'numero.required'    => 'El número de habitación es obligatorio.',
            'numero.unique'      => 'Este número de habitación ya existe.',
            'tipo_id.required'   => 'El tipo de habitación es obligatorio.',
            'tipo_id.exists'     => 'El tipo seleccionado no existe.',
            'estado_id.required' => 'El estado es obligatorio.',
            'estado_id.exists'   => 'El estado seleccionado no existe.',
        ]);

        if ($this->tieneHistorialReservas($habitacion->numero)) {
            $cambiaNumero = (int) $request->numero !== (int) $habitacion->numero;
            $cambiaTipo   = (int) $request->tipo_id !== (int) $habitacion->tipo_id;

            if ($cambiaNumero || $cambiaTipo) {
                return redirect()->route('habitaciones.index')
                    ->with('error', 'Esta habitación tiene historial de reservas: no se puede cambiar su número ni su tipo. Solo puede actualizar su estado o desactivarla.');
            }
        }

        if ((int) $request->numero !== (int) $habitacion->numero) {
            try {
                DB::transaction(function () use ($request, $habitacion) {
                    if ($this->tieneReservaActiva($habitacion->numero)) {
                        throw new \RuntimeException('La habitación pasó a tener una reserva vigente durante la edición. Intente nuevamente.');
                    }

                    Habitacion::create([
                        'numero'     => $request->numero,
                        'tipo_id'    => $request->tipo_id,
                        'estado_id'  => $request->estado_id,
                        'activo'     => $request->activo,
                        'created_at' => $habitacion->created_at,
                    ]);

                    $habitacion->delete();
                });
            } catch (\RuntimeException $e) {
                return redirect()->route('habitaciones.index')
                    ->with('error', $e->getMessage());
            }
        } else {
            $habitacion->update([
                'tipo_id'   => $request->tipo_id,
                'estado_id' => $request->estado_id,
                'activo'    => $request->activo,
            ]);
        }

        return redirect()->route('habitaciones.index')
            ->with('exito', 'Habitación actualizada correctamente.')
            ->with('pagina_retorno', $request->input('pagina_actual', 1));
    }

    public function destroy(Habitacion $habitacion)
    {
        if ($this->tieneReservaActiva($habitacion->numero) || $this->tieneReservaPendiente($habitacion->numero)) {
            return redirect()->route('habitaciones.index')
                ->with('error', 'No se puede eliminar: la habitación tiene una reserva pendiente o activa.');
        }

        if ($this->tieneHistorialReservas($habitacion->numero)) {
            return redirect()->route('habitaciones.index')
                ->with('error', 'No se puede eliminar: la habitación tiene historial de reservas. Puede desactivarla en su lugar.');
        }

        $habitacion->delete();
        return redirect()->route('habitaciones.index')
            ->with('exito', 'Habitación eliminada correctamente.');
    }

    public function filtrar(Request $request)
    {   
        $query = Habitacion::with('tipo', 'estado')->orderBy('numero');

        if ($request->filled('numero')) {
            $query->where('numero', $request->numero);
        }
        if ($request->filled('piso')) {
            $piso = (int) $request->piso;
            $query->whereBetween('numero', [$piso * 100, $piso * 100 + 99]);
        }
        if ($request->filled('tipo_id')) {
            $query->where('tipo_id', $request->tipo_id);
        }
        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->estado_id);
        }
        if ($request->filled('activo') && $request->activo !== '') {
            $query->where('activo', $request->activo);
        }

        $porPagina = 6;
        $pagina    = (int) $request->get('pagina', 1);
        $total     = $query->count();
        $items     = $query->skip(($pagina - 1) * $porPagina)->take($porPagina)->get();
        
        $idEstadoActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idEstadoPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');

        $numerosActivos = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->where('reservas.estado_id', $idEstadoActiva)
            ->pluck('reserva_habitaciones.habitacion_numero')
            ->unique();

        $numerosPendientes = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->where('reservas.estado_id', $idEstadoPendiente)
            ->pluck('reserva_habitaciones.habitacion_numero')
            ->unique();

        $numerosConHistorial = DB::table('reserva_habitaciones')
            ->pluck('habitacion_numero')
            ->unique();

        return response()->json([
            'data' => $items->map(fn($h) => [
                'numero'                  => $h->numero,
                'tipo_id'                 => $h->tipo_id,
                'tipo_nombre'             => $h->tipo->nombre,
                'precio_hora'             => number_format($h->tipo->precio_hora, 2),
                'precio_noche'            => number_format($h->tipo->precio_noche, 2),
                'estado_id'               => $h->estado_id,
                'estado_nombre'           => $h->estado->nombre,
                'activo'                  => $h->activo ? 1 : 0,
                'tiene_reserva_activa'    => $numerosActivos->contains($h->numero),
                'tiene_reserva_pendiente' => $numerosPendientes->contains($h->numero),
                'tiene_historial'         => $numerosConHistorial->contains($h->numero),
            ]),
            'total'         => $total,
            'por_pagina'    => $porPagina,
            'pagina_actual' => $pagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    }
}
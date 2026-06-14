<?php

namespace App\Http\Controllers\Habitaciones;

use App\Http\Controllers\Controller;
use App\Models\EstadoHabitacion;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use Illuminate\Http\Request;

class HabitacionController extends Controller
{
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

        if ((int)$request->numero !== (int)$habitacion->numero) {
            Habitacion::create([
                'numero'    => $request->numero,
                'tipo_id'   => $request->tipo_id,
                'estado_id' => $request->estado_id,
                'activo'    => $request->activo,
            ]);
            $habitacion->delete();
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

        return response()->json([
            'data' => $items->map(fn($h) => [
                'numero'        => $h->numero,
                'tipo_id'       => $h->tipo_id,
                'tipo_nombre'   => $h->tipo->nombre,
                'precio_hora'   => number_format($h->tipo->precio_hora, 2),
                'precio_noche'  => number_format($h->tipo->precio_noche, 2),
                'estado_id'     => $h->estado_id,
                'estado_nombre' => $h->estado->nombre,
                'activo'        => $h->activo ? 1 : 0,
            ]),
            'total'         => $total,
            'por_pagina'    => $porPagina,
            'pagina_actual' => $pagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    }
}
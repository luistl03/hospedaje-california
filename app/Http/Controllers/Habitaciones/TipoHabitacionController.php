<?php

namespace App\Http\Controllers\Habitaciones;

use App\Http\Controllers\Controller;
use App\Models\TipoHabitacion;
use App\Models\EstadoReserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoHabitacionController extends Controller
{
    // Verifica por AJAX si ya existe un tipo con ese nombre
    public function verificarNombre(Request $request)
    {
        $existe = TipoHabitacion::where('nombre', $request->nombre)
            ->where('id', '!=', $request->id ?? 0)
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'       => 'required|string|max:50|unique:tipos_habitacion,nombre',
            'precio_hora'  => 'required|numeric|min:0',
            'precio_noche' => 'required|numeric|min:0',
            'max_huespedes' => 'required|integer|min:1',
            'descripcion'  => 'nullable|string|max:255',
        ], [
            'nombre.required'       => 'El nombre del tipo es obligatorio.',
            'nombre.unique'         => 'Ya existe un tipo con ese nombre.',
            'precio_hora.required'  => 'El precio por hora es obligatorio.',
            'precio_hora.numeric'   => 'El precio por hora debe ser un número.',
            'precio_noche.required' => 'El precio por noche es obligatorio.',
            'precio_noche.numeric'  => 'El precio por noche debe ser un número.',
            'max_huespedes.required' => 'El máximo de huéspedes es obligatorio.',
            'max_huespedes.integer'  => 'Debe ser un número entero.',
            'max_huespedes.min'      => 'El mínimo permitido es 1.',
        ]);

        TipoHabitacion::create([
            'nombre'       => $request->nombre,
            'precio_hora'  => $request->precio_hora,
            'precio_noche' => $request->precio_noche,
            'max_huespedes' => $request->max_huespedes,
            'descripcion'  => $request->descripcion,
            'activo'       => 1,
        ]);

        return redirect()->route('habitaciones.index')
            ->with('exito', 'Tipo de habitación creado correctamente.');
    }
    
    // Verifica si el tipo tiene habitaciones activas o en uso asignadas
    private function tieneHabitacionesEnUsoPorTipo(TipoHabitacion $tipoHabitacion): bool
    {
        $estadosBloqueo = ['disponible', 'ocupada', 'reservada', 'limpieza'];

        return $tipoHabitacion->habitaciones()
            ->where('activo', 1)
            ->whereHas('estado', fn($q) => $q->whereIn('nombre', $estadosBloqueo))
            ->exists();
    }

    public function update(Request $request, TipoHabitacion $tipoHabitacion)
    {
        $request->validate([
            'nombre'       => 'required|string|max:50|unique:tipos_habitacion,nombre,' . $tipoHabitacion->id,
            'precio_hora'  => 'required|numeric|min:0',
            'precio_noche' => 'required|numeric|min:0',
            'max_huespedes' => 'required|integer|min:1',
            'descripcion'  => 'nullable|string|max:255',
            'activo'       => 'required|in:0,1',
        ], [
            'nombre.required'       => 'El nombre del tipo es obligatorio.',
            'nombre.unique'         => 'Ya existe un tipo con ese nombre.',
            'precio_hora.required'  => 'El precio por hora es obligatorio.',
            'precio_hora.numeric'   => 'El precio por hora debe ser un número.',
            'precio_noche.required' => 'El precio por noche es obligatorio.',
            'precio_noche.numeric'  => 'El precio por noche debe ser un número.',
            'max_huespedes.required'=> 'El máximo de huéspedes es obligatorio.',
            'max_huespedes.integer' => 'Debe ser un número entero.',
            'max_huespedes.min'     => 'El mínimo permitido es 1.',
        ]);

        if ((int) $request->activo === 0 && (bool) $tipoHabitacion->activo === true) {
            if ($this->tieneHabitacionesEnUsoPorTipo($tipoHabitacion)) {
                return redirect()->route('habitaciones.index')
                    ->with('error', 'No puedes desactivar este tipo porque tiene habitaciones activas o en uso asignadas.');
            }
        }

        if ($this->tieneHabitacionesEnUsoPorTipo($tipoHabitacion)) {
            $cambiaPrecioHora   = (float) $request->precio_hora   !== (float) $tipoHabitacion->precio_hora;
            $cambiaPrecioNoche  = (float) $request->precio_noche  !== (float) $tipoHabitacion->precio_noche;
            $cambiaMaxHuespedes = (int)   $request->max_huespedes !== (int)   $tipoHabitacion->max_huespedes;

            if ($cambiaPrecioHora || $cambiaPrecioNoche || $cambiaMaxHuespedes) {
                return redirect()->route('habitaciones.index')
                    ->with('error', 'No se puede cambiar el precio ni el máximo de huéspedes: hay habitaciones activas o en uso asignadas a este tipo.');
            }
        }

        $tipoHabitacion->update([
            'nombre'       => $request->nombre,
            'precio_hora'  => $request->precio_hora,
            'precio_noche' => $request->precio_noche,
            'max_huespedes' => $request->max_huespedes,
            'descripcion'  => $request->descripcion,
            'activo'       => $request->activo,
        ]);

        return redirect()->route('habitaciones.index')
            ->with('exito', 'Tipo de habitación actualizado correctamente.');
    }

    public function destroy(TipoHabitacion $tipoHabitacion)
    {
        if ($tipoHabitacion->habitaciones()->exists()) {
            return redirect()->route('habitaciones.index')
                ->with('error', 'No puedes eliminar este tipo porque tiene habitaciones asignadas.');
        }

        $tipoHabitacion->delete();
        return redirect()->route('habitaciones.index')
            ->with('exito', 'Tipo de habitación eliminado correctamente.');
    }
}
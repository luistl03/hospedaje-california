<?php

namespace App\Http\Controllers\Huespedes;

use App\Http\Controllers\Controller;
use App\Models\Huesped;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HuespedController extends Controller
{
    // Verifica si el huésped tiene una reserva pendiente o activa (como acompañante o principal)
    private function tieneReservasVigentes(string $numDoc): bool
    {
        $idsBloqueo = DB::table('estados_reserva')
            ->whereIn('nombre', ['pendiente', 'activa'])
            ->pluck('id');

        $comoAcompanante = DB::table('reserva_huespedes')
            ->join('reservas', 'reservas.id', '=', 'reserva_huespedes.reserva_id')
            ->where('reserva_huespedes.huesped_num_doc', $numDoc)
            ->whereIn('reservas.estado_id', $idsBloqueo)
            ->exists();

        if ($comoAcompanante) {
            return true;
        }

        // huesped_principal no tiene FK, se verifica aparte
        return DB::table('reservas')
            ->where('huesped_principal', $numDoc)
            ->whereIn('estado_id', $idsBloqueo)
            ->exists();
    }

    // Verifica si el huésped aparece en cualquier reserva, vigente o no (como acompañante o principal)
    private function tieneHistorialReservas(string $numDoc): bool
    {
        $comoAcompanante = DB::table('reserva_huespedes')
            ->where('huesped_num_doc', $numDoc)
            ->exists();

        if ($comoAcompanante) {
            return true;
        }

        // huesped_principal no tiene FK, se verifica aparte
        return DB::table('reservas')
            ->where('huesped_principal', $numDoc)
            ->exists();
    }

    public function index()
    {
        return view('huespedes.index');
    }

    // Verifica por AJAX si el número de documento ya existe
    public function verificarDocumento(Request $request)
    {
        $existe = Huesped::where('num_doc', $request->num_doc)
            ->when($request->filled('num_doc_original'), function ($q) use ($request) {
                $q->where('num_doc', '!=', $request->num_doc_original);
            })
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    // Verifica por AJAX si el teléfono ya existe
    public function verificarTelefono(Request $request)
    {
        $existe = Huesped::whereNotNull('telefono')
            ->where('telefono', $request->telefono)
            ->where('num_doc', '!=', $request->num_doc_original ?? '')
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'num_doc'  => 'required|string|max:20|unique:huespedes,num_doc',
            'telefono' => 'nullable|string|max:15|unique:huespedes,telefono',
        ], [
            'nombre.required'  => 'El nombre es obligatorio.',
            'num_doc.required' => 'El número de documento es obligatorio.',
            'num_doc.unique'   => 'Este documento ya está registrado.',
            'telefono.unique'  => 'Este teléfono ya está registrado.',
        ]);

        Huesped::create([
            'nombre'   => $request->nombre,
            'num_doc'  => $request->num_doc,
            'telefono' => $request->telefono,
            'activo'   => 1,
        ]);

        return redirect()->route('huespedes.index')
            ->with('exito', 'Huésped registrado correctamente.');
    }

    public function update(Request $request, Huesped $huesped)
    {
        // Bloquea edición si el huésped tiene una reserva vigente
        if ($this->tieneReservasVigentes($huesped->num_doc)) {
            return redirect()->route('huespedes.index')
                ->with('error', 'No se puede editar: el huésped tiene una reserva pendiente o activa.');
        }

        $request->validate([
            'nombre'   => 'required|string|max:100',
            'num_doc'  => 'required|string|max:20|unique:huespedes,num_doc,' . $huesped->num_doc . ',num_doc',
            'telefono' => 'nullable|string|max:15|unique:huespedes,telefono,' . $huesped->num_doc . ',num_doc',
            'activo'   => 'required|in:0,1',
        ], [
            'nombre.required'  => 'El nombre es obligatorio.',
            'num_doc.required' => 'El número de documento es obligatorio.',
            'num_doc.unique'   => 'Este documento ya está registrado.',
            'telefono.unique'  => 'Este teléfono ya está registrado.',
        ]);

        $cambiaNumDoc = $request->num_doc !== $huesped->num_doc;

        // Bloquea el cambio de num_doc si el huésped tiene historial de reservas
        if ($cambiaNumDoc && $this->tieneHistorialReservas($huesped->num_doc)) {
            return redirect()->route('huespedes.index')
                ->with('error', 'Este huésped tiene historial de reservas: no se puede cambiar su número de documento. Solo puede actualizar su nombre, teléfono o estado.');
        }

        // huesped_principal no tiene FK con cascada, se sincroniza a mano
        if ($cambiaNumDoc) {
            DB::transaction(function () use ($request, $huesped) {
                DB::table('reservas')
                    ->where('huesped_principal', $huesped->num_doc)
                    ->update(['huesped_principal' => $request->num_doc]);

                $huesped->update([
                    'nombre'   => $request->nombre,
                    'num_doc'  => $request->num_doc,
                    'telefono' => $request->telefono,
                    'activo'   => $request->activo,
                ]);
            });
        } else {
            $huesped->update([
                'nombre'   => $request->nombre,
                'telefono' => $request->telefono,
                'activo'   => $request->activo,
            ]);
        }

        return redirect()->route('huespedes.index')
            ->with('exito', 'Huésped actualizado correctamente.')
            ->with('pagina_retorno', $request->input('pagina_actual', 1));
    }

    public function destroy(Huesped $huesped)
    {
        if ($this->tieneReservasVigentes($huesped->num_doc)) {
            return redirect()->route('huespedes.index')
                ->with('error', 'No se puede eliminar: el huésped tiene una reserva pendiente o activa.');
        }

        if ($this->tieneHistorialReservas($huesped->num_doc)) {
            return redirect()->route('huespedes.index')
                ->with('error', 'No se puede eliminar: el huésped tiene historial de reservas. Puede desactivarlo en su lugar.');
        }

        $huesped->delete();
        return redirect()->route('huespedes.index')
            ->with('exito', 'Huésped eliminado correctamente.');
    }

    public function filtrar(Request $request)
    {
        $query = Huesped::query()->orderBy('nombre');

        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }
        if ($request->filled('num_doc')) {
            $query->where('num_doc', 'like', '%' . $request->num_doc . '%');
        }
        if ($request->filled('telefono')) {
            $query->where('telefono', 'like', '%' . $request->telefono . '%');
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
                'num_doc'  => $h->num_doc,
                'nombre'   => $h->nombre,
                'telefono' => $h->telefono ?? '—',
                'activo'   => $h->activo ? 1 : 0,
            ]),
            'total'         => $total,
            'por_pagina'    => $porPagina,
            'pagina_actual' => $pagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    }

    // Búsqueda liviana de huéspedes activos, usada desde Reservas
    public function buscar(Request $request)
    {
        $query = Huesped::where('activo', 1);

        if ($request->filled('num_doc')) {
            $query->where('num_doc', $request->num_doc);
        } elseif ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        } else {
            return response()->json(['data' => []]);
        }

        $items = $query->limit(10)->get();

        return response()->json([
            'data' => $items->map(fn($h) => [
                'num_doc'  => $h->num_doc,
                'nombre'   => $h->nombre,
                'telefono' => $h->telefono ?? '—',
            ]),
        ]);
    }
}
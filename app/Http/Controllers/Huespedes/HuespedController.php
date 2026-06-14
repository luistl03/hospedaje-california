<?php

namespace App\Http\Controllers\Huespedes;

use App\Http\Controllers\Controller;
use App\Models\Huesped;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class HuespedController extends Controller
{
    public function index()
    {
        $tiposDocumento = TipoDocumento::orderBy('nombre')->get();

        return view('huespedes.index', compact('tiposDocumento'));
    }

    public function verificarDocumento(Request $request)
    {
        $existe = Huesped::where('tipo_doc_id', $request->tipo_doc_id)
            ->where('num_doc', $request->num_doc)
            ->where('id', '!=', $request->id ?? 0)
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    public function verificarTelefono(Request $request)
    {
        $existe = Huesped::whereNotNull('telefono')
            ->where('telefono', $request->telefono)
            ->where('id', '!=', $request->id ?? 0)
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'tipo_doc_id' => 'required|exists:tipos_documento,id',
            'num_doc'     => 'required|string|max:20|unique:huespedes,num_doc,NULL,id,tipo_doc_id,' . $request->tipo_doc_id,
            'telefono'    => 'nullable|string|max:15',
        ], [
            'nombre.required'      => 'El nombre es obligatorio.',
            'tipo_doc_id.required' => 'El tipo de documento es obligatorio.',
            'tipo_doc_id.exists'   => 'El tipo de documento no es válido.',
            'num_doc.required'     => 'El número de documento es obligatorio.',
            'num_doc.unique'       => 'Este documento ya está registrado.',
        ]);

        Huesped::create([
            'nombre'      => $request->nombre,
            'tipo_doc_id' => $request->tipo_doc_id,
            'num_doc'     => $request->num_doc,
            'telefono'    => $request->telefono,
            'activo'      => 1,
        ]);

        return redirect()->route('huespedes.index')
            ->with('exito', 'Huésped registrado correctamente.');
    }

    public function update(Request $request, Huesped $huesped)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'tipo_doc_id' => 'required|exists:tipos_documento,id',
            'num_doc'     => 'required|string|max:20|unique:huespedes,num_doc,' . $huesped->id . ',id,tipo_doc_id,' . $request->tipo_doc_id,
            'telefono'    => 'nullable|string|max:15',
            'activo'      => 'required|in:0,1',
        ], [
            'nombre.required'      => 'El nombre es obligatorio.',
            'tipo_doc_id.required' => 'El tipo de documento es obligatorio.',
            'tipo_doc_id.exists'   => 'El tipo de documento no es válido.',
            'num_doc.required'     => 'El número de documento es obligatorio.',
            'num_doc.unique'       => 'Este documento ya está registrado.',
        ]);
/*
        if ((int) $request->activo === 0 && (bool) $huesped->activo === true) {
            $tieneReservasActivas = $huesped->reservas()
            ->whereHas('estado', fn($q) => $q->whereIn('nombre', ['pendiente', 'activa']))
            ->exists();

            if ($tieneReservasActivas) {
                return redirect()->route('huespedes.index')
                    ->with('error', 'No puedes desactivar este huésped porque tiene reservas activas.');
            }
        }
*/
        $huesped->update([
            'nombre'      => $request->nombre,
            'tipo_doc_id' => $request->tipo_doc_id,
            'num_doc'     => $request->num_doc,
            'telefono'    => $request->telefono,
            'activo'      => $request->activo,
        ]);

        return redirect()->route('huespedes.index')
            ->with('exito', 'Huésped actualizado correctamente.')
            ->with('pagina_retorno', $request->input('pagina_actual', 1));
    }

    public function destroy(Huesped $huesped)
    {/*
        if ($huesped->reservas()->exists()) {
            return redirect()->route('huespedes.index')
                ->with('error', 'No puedes eliminar este huésped porque tiene reservas registradas.');
        }
*/
        $huesped->delete();

        return redirect()->route('huespedes.index')
            ->with('exito', 'Huésped eliminado correctamente.');
    }

    public function filtrar(Request $request)
    {
        $query = Huesped::with('tipoDocumento')->orderBy('nombre');

        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }
        if ($request->filled('tipo_doc_id')) {
            $query->where('tipo_doc_id', $request->tipo_doc_id);
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
                'id'          => $h->id,
                'nombre'      => $h->nombre,
                'tipo_doc_id' => $h->tipo_doc_id,
                'tipo_doc'    => $h->tipoDocumento->nombre ?? '—',
                'num_doc'     => $h->num_doc,
                'telefono'    => $h->telefono ?? '—',
                'activo'      => $h->activo ? 1 : 0,
            ]),
            'total'         => $total,  
            'por_pagina'    => $porPagina,
            'pagina_actual' => $pagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    }

    public function buscar(Request $request)
    {
        $query = Huesped::with('tipoDocumento')->where('activo', 1);

        if ($request->filled('tipo_doc_id') && $request->filled('num_doc')) {
            $query->where('tipo_doc_id', $request->tipo_doc_id)
                ->where('num_doc', $request->num_doc);
        } elseif ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        } else {
            return response()->json(['data' => []]);
        }

        $items = $query->limit(10)->get();

        return response()->json([
            'data' => $items->map(fn($h) => [
                'id'       => $h->id,
                'nombre'   => $h->nombre,
                'tipo_doc' => $h->tipoDocumento->nombre ?? '—',
                'num_doc'  => $h->num_doc,
                'telefono' => $h->telefono ?? '—',
            ]),
        ]);
    }
}
<?php

namespace App\Http\Controllers\Sugerencias;

use App\Http\Controllers\Controller;
use App\Models\Sugerencia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SugerenciaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'num_doc'    => 'required|string|max:20',
            'comentario' => 'required|string|max:255',
        ]);

        Sugerencia::create([
            'num_doc'    => $request->num_doc,
            'comentario' => $request->comentario,
        ]);

        return response()->json(['ok' => true]);
    }
}
<?php
// Ubicación destino: app/Http/Controllers/Predicciones/PrediccionController.php

namespace App\Http\Controllers\Predicciones;

use App\Http\Controllers\Controller;
use App\Services\PrediccionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrediccionController extends Controller
{
    // Opciones válidas que puede elegir el gerente en los selects del front
    private const OPCIONES_HISTORICO = [6, 12, 24, 36, 48, 60];
    private const OPCIONES_PROYECCION = [1, 3, 6, 12];

    public function __construct(private PrediccionService $prediccionService)
    {
    }

    public function index(Request $request)
    {
        [$mesesHistorico, $mesesProyeccion] = $this->parametros($request);

        $predicciones = $this->prediccionService->generar($mesesHistorico, $mesesProyeccion);

        return view('predicciones.index', [
            'predicciones' => $predicciones,
            'mesesHistorico' => $mesesHistorico,
            'mesesProyeccion' => $mesesProyeccion,
            'opcionesHistorico' => self::OPCIONES_HISTORICO,
            'opcionesProyeccion' => self::OPCIONES_PROYECCION,
        ]);
    }

    public function datos(Request $request): JsonResponse
    {
        [$mesesHistorico, $mesesProyeccion] = $this->parametros($request);

        return response()->json($this->prediccionService->generar($mesesHistorico, $mesesProyeccion));
    }

    // Lee y valida los parámetros que llegan por query string, con defaults seguros
    private function parametros(Request $request): array
    {
        $mesesHistorico = (int) $request->input('meses_historico', 24);
        $mesesProyeccion = (int) $request->input('meses_proyeccion', 6);

        if (!in_array($mesesHistorico, self::OPCIONES_HISTORICO, true)) {
            $mesesHistorico = 24;
        }

        if (!in_array($mesesProyeccion, self::OPCIONES_PROYECCION, true)) {
            $mesesProyeccion = 6;
        }

        return [$mesesHistorico, $mesesProyeccion];
    }
}
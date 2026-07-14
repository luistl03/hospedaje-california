<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\EstadoReserva;
use App\Models\Habitacion;
use App\Models\EstadoHabitacion;
use App\Models\Huesped;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Models\MetodoPago;
use App\Models\Extension;
use App\Models\TipoComprobante;
use App\Models\Comprobante;
use App\Models\Devolucion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReservaController extends Controller
{
    // Detecta conflictos de horario para una o más habitaciones (con buffer de 30 min)
    private function queryConflictoHorario($entrada, $salida, ?int $excluirReservaId = null)
    {
        $idActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');

        $query = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])
            ->where('reservas.fecha_entrada', '<', $salida)
            ->whereRaw('DATE_ADD(reserva_habitaciones.fecha_salida_efectiva, INTERVAL 30 MINUTE) > ?', [$entrada]);

        if ($excluirReservaId) {
            $query->where('reservas.id', '!=', $excluirReservaId);
        }

        return $query;
    }

    // Replica en servidor la detección de franja horaria del JS (detectarFranja en index.js)
    private function detectarFranja(\DateTime $entrada): string
    {
        $horaEnMinutos = ((int) $entrada->format('H')) * 60 + ((int) $entrada->format('i'));

        $MADRUGADA_FIN  = 6 * 60;
        $EARLY_INICIO   = 6 * 60 + 1;
        $EARLY_FIN      = 11 * 60;
        $CHECKIN_NORMAL = 13 * 60;

        if ($horaEnMinutos >= 0 && $horaEnMinutos <= $MADRUGADA_FIN)        return 'madrugada';
        if ($horaEnMinutos >= $EARLY_INICIO && $horaEnMinutos <= $EARLY_FIN) return 'early';
        if ($horaEnMinutos > $EARLY_FIN && $horaEnMinutos < $CHECKIN_NORMAL) return 'intermedio';
        return 'normal';
    }

    // Recalcula y persiste costo_total y saldo_pendiente de la reserva
    private function recalcularMontos(Reserva $reserva): void
    {
        $reserva->load(['habitaciones', 'pagos.tipo', 'devoluciones', 'estado']);

        $montoHabitaciones = $reserva->habitaciones->sum('pivot.precio_aplicado');
        $montoEarly         = (float) $reserva->monto_recargo;
        $montoExtensiones   = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'extension')
            ->sum('monto');
        $montoPagado        = $reserva->pagos->sum('monto');

        $ajusteDevoluciones = $reserva->devoluciones->sum(fn($d) => $d->monto_devuelto + $d->monto_retenido);

        $costoTotal = round($montoHabitaciones + $montoEarly + $montoExtensiones, 2);
        $saldo      = round($costoTotal - $montoPagado + $ajusteDevoluciones, 2);

        if ($reserva->estado->nombre === 'cancelada') {
            $saldo = 0;
        }

        $reserva->update([
            'costo_total'     => $costoTotal,
            'saldo_pendiente' => $saldo,
        ]);
    }

    // Valida un RUC peruano (11 dígitos, prefijo y dígito verificador)
    private function esRucValido(string $ruc): bool
    {
        if (!preg_match('/^(10|15|17|20)\d{9}$/', $ruc)) {
            return false;
        }

        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma  = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $ruc[$i] * $pesos[$i];
        }

        $resto     = $suma % 11;
        $resultado = 11 - $resto;
        $digitoVerificador = match (true) {
            $resultado === 10 => 0,
            $resultado === 11 => 1,
            default           => $resultado,
        };

        return $digitoVerificador === (int) $ruc[10];
    }

    // Genera el correlativo y crea el comprobante (B001 boleta / F001 factura)
    private function generarComprobante(string $tipoNombre, ?string $ruc = null, ?string $razonSocial = null): Comprobante
    {
        $serie = $tipoNombre === 'boleta' ? 'B001' : 'F001';

        $ultimoNumero = DB::table('comprobantes')
            ->where('serie', $serie)
            ->lockForUpdate()
            ->max('numero');

        $siguiente = $ultimoNumero ? ((int) $ultimoNumero) + 1 : 1;
        $numero    = str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);

        $tipoId = TipoComprobante::where('nombre', $tipoNombre)->value('id');

        return Comprobante::create([
            'serie'         => $serie,
            'numero'        => $numero,
            'fecha_emision' => now(),
            'tipo_id'       => $tipoId,
            'ruc'           => $ruc,
            'razon_social'  => $razonSocial,
        ]);
    }

    public function index()
    {
        $estadosReserva = EstadoReserva::all();
        $metodosPago    = MetodoPago::all();

        return view('reservas.index', compact('estadosReserva', 'metodosPago'));
    }

    public function habitacionesDisponibles(Request $request)
    {
        $request->validate([
            'fecha_entrada' => 'required|date',
            'fecha_salida'  => 'required|date|after:fecha_entrada',
            'tipo_estadia'  => 'required|in:horas,noches',
        ]);

        $entrada = $request->fecha_entrada;
        $salida  = $request->fecha_salida;

        // Habitaciones ocupadas en ese rango (+30min buffer)
        $ocupadas = $this->queryConflictoHorario($entrada, $salida)
            ->pluck('reserva_habitaciones.habitacion_numero')
            ->toArray();

        $idsNoAptos = EstadoHabitacion::whereIn('nombre', ['limpieza', 'mantenimiento'])->pluck('id');

        $habitaciones = Habitacion::with('tipo')
            ->where('activo', 1)
            ->whereNotIn('estado_id', $idsNoAptos)
            ->whereNotIn('numero', $ocupadas)
            ->orderBy('numero')
            ->get();

        $pisos = $habitaciones->groupBy(fn($h) => intdiv($h->numero, 100))
            ->map(function ($habs, $piso) {
                return [
                    'piso'         => $piso,
                    'habitaciones' => $habs->map(fn($h) => [
                        'numero'           => $h->numero,
                        'tipo_nombre'      => $h->tipo->nombre,
                        'precio_hora'      => number_format($h->tipo->precio_hora, 2),
                        'precio_noche'     => number_format($h->tipo->precio_noche, 2),
                        'precio_hora_raw'  => (float) $h->tipo->precio_hora,
                        'precio_noche_raw' => (float) $h->tipo->precio_noche,
                        'max_huespedes'    => $h->tipo->max_huespedes,
                    ])->values(),
                ];
            })->values();

        return response()->json([
            'pisos'       => $pisos,
            'tipo_nombre' => $request->tipo_estadia,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo_estadia'          => 'required|in:horas,noches',
            'fecha_entrada'         => 'required|date',
            'fecha_salida'          => 'required|date|after:fecha_entrada',
            'habitaciones'          => 'required|array|min:1',
            'habitaciones.*.numero' => 'required|integer|exists:habitaciones,numero',
            'huespedes'             => 'required|array|min:1',
            'huespedes.*'           => 'distinct|string|exists:huespedes,num_doc',
            'huesped_principal'     => 'required|string|exists:huespedes,num_doc',
            'monto_pago'            => 'required|numeric|min:0',
            'metodo_id'             => 'required|exists:metodos_pago,id',
            'numero_operacion'      => 'nullable|string|max:30',
        ]);

        if (! in_array($request->huesped_principal, $request->huespedes)) {
            return response()->json([
                'error' => 'El huésped principal debe estar dentro de la lista de huéspedes agregados.'
            ], 422);
        }

        $metodo = MetodoPago::findOrFail($request->metodo_id);
        $esMetodoEfectivo = $metodo->nombre === 'efectivo';

        if (!$esMetodoEfectivo && !$request->filled('numero_operacion')) {
            return response()->json([
                'error' => 'El número de operación es obligatorio para este método de pago.'
            ], 422);
        }

        $entrada     = new \DateTime($request->fecha_entrada);
        $salida      = new \DateTime($request->fecha_salida);
        $ahora       = new \DateTime();

        if ($entrada < $ahora) {
            return response()->json([
                'error' => 'La fecha de entrada no puede ser anterior a la fecha y hora actual.'
            ], 422);
        }

        $diffMinutos = ($entrada->getTimestamp() - $ahora->getTimestamp()) / 60;
        $esInmediata = $diffMinutos <= 10;

        $tipoNombre = $request->tipo_estadia;
        $esPorHoras = $tipoNombre === 'horas';

        // La franja y las unidades se recalculan en servidor, no se confían del cliente
        $franja = $esPorHoras ? 'horas' : $this->detectarFranja($entrada);

        if ($esPorHoras) {
            $unidades = (int) round(($salida->getTimestamp() - $entrada->getTimestamp()) / 3600);
        } else {
            $entDia   = new \DateTime($entrada->format('Y-m-d'));
            $salDia   = new \DateTime($salida->format('Y-m-d'));
            $diff     = $entDia->diff($salDia)->days;
            $unidades = $franja === 'madrugada'
                ? ($diff === 0 ? 1 : $diff + 1)
                : ($diff < 1 ? 1 : $diff);
        }

        $idsNoAptos = EstadoHabitacion::whereIn('nombre', ['limpieza', 'mantenimiento'])->pluck('id');

        foreach ($request->habitaciones as $hab) {
            $numero        = $hab['numero'];
            $habitacionObj = Habitacion::where('numero', $numero)->first();

            if (!$habitacionObj || !$habitacionObj->activo || $idsNoAptos->contains($habitacionObj->estado_id)) {
                return response()->json([
                    'error' => "La habitación N°{$numero} no está disponible actualmente (en limpieza, mantenimiento o inactiva)."
                ], 422);
            }

            $conflicto = $this->queryConflictoHorario($request->fecha_entrada, $request->fecha_salida)
                ->where('reserva_habitaciones.habitacion_numero', $numero)
                ->exists();

            if ($conflicto) {
                return response()->json([
                    'error' => "La habitación N°{$numero} ya no está disponible para el rango seleccionado. Actualice la búsqueda e intente nuevamente."
                ], 422);
            }
        }

        $montoBase  = 0;
        $montoEarly = 0;
        $maxTotal   = 0;

        foreach ($request->habitaciones as $hab) {
            $numero     = $hab['numero'];
            $habitacion = Habitacion::with('tipo')->where('numero', $numero)->firstOrFail();
            $maxTotal  += $habitacion->tipo->max_huespedes;

            if ($esPorHoras) {
                $montoBase += $habitacion->tipo->precio_hora * $unidades;
            } else {
                $montoBase += $habitacion->tipo->precio_noche * $unidades;
                if ($franja === 'early') {
                    $montoEarly += $habitacion->tipo->precio_hora * 2;
                }
            }
        }

        if (count($request->huespedes) > $maxTotal) {
            return response()->json([
                'error' => "Has excedido el límite de huéspedes permitido ({$maxTotal}) para las habitaciones seleccionadas."
            ], 422);
        }

        $montoTotal  = $montoBase + $montoEarly;
        $montoMinimo = $esInmediata ? $montoTotal : round($montoTotal * 0.5, 2);

        if ($request->monto_pago < $montoMinimo) {
            return response()->json([
                'error' => 'El monto ingresado es menor al mínimo requerido (S/ ' . number_format($montoMinimo, 2) . ').'
            ], 422);
        }

        if ($request->monto_pago > $montoTotal) {
            return response()->json([
                'error' => 'El monto ingresado supera el total de la reserva (S/ ' . number_format($montoTotal, 2) . ').'
            ], 422);
        }

        DB::transaction(function () use (
            $request, $esInmediata, $esPorHoras, $franja, $unidades, $montoEarly, $montoTotal
        ) {
            $estadoNombre = $esInmediata ? 'activa' : 'pendiente';
            $estadoId     = EstadoReserva::where('nombre', $estadoNombre)->value('id');

            $reserva = Reserva::create([
                'usuario_id'        => auth()->id(),
                'huesped_principal' => $request->huesped_principal,
                'fecha_entrada'     => $request->fecha_entrada,
                'fecha_salida'      => $request->fecha_salida,
                'estado_id'         => $estadoId,
                'es_por_horas'      => $esPorHoras,
                'costo_total'       => 0,
                'saldo_pendiente'   => 0,
                'monto_recargo'     => $franja === 'early' ? $montoEarly : 0,
                'observacion'       => $request->observacion,
            ]);

            foreach ($request->habitaciones as $hab) {
                $habitacion = Habitacion::with('tipo')->where('numero', $hab['numero'])->first();
                $precio     = $esPorHoras
                    ? $habitacion->tipo->precio_hora * $unidades
                    : $habitacion->tipo->precio_noche * $unidades;

                $reserva->habitaciones()->attach($hab['numero'], [
                    'precio_aplicado'       => $precio,
                    'tiempo_estadia'        => $unidades,
                    'fecha_salida_efectiva' => $request->fecha_salida,
                    'tipo_nombre_historico' => $habitacion->tipo->nombre,
                ]);

                if ($esInmediata) {
                    $idOcupada = EstadoHabitacion::where('nombre', 'ocupada')->value('id');
                    $habitacion->update(['estado_id' => $idOcupada]);
                }
            }

            $reserva->huespedes()->attach($request->huespedes);

            $idAdelanto        = TipoPago::where('nombre', 'adelanto')->value('id');
            $idPagoFinal       = TipoPago::where('nombre', 'pago final')->value('id');
            $idIngresoTemprano = TipoPago::where('nombre', 'ingreso temprano')->value('id');
            $numOp             = $request->numero_operacion;

            $montoParaEarly = $franja === 'early' ? min($request->monto_pago, $montoEarly) : 0;

            if ($montoParaEarly > 0) {
                Pago::create([
                    'reserva_id'       => $reserva->id,
                    'monto'            => $montoParaEarly,
                    'metodo_id'        => $request->metodo_id,
                    'tipo_id'          => $idIngresoTemprano,
                    'fecha_pago'       => now(),
                    'numero_operacion' => $numOp,
                ]);
            }

            $montoPrincipal = round($request->monto_pago - $montoParaEarly, 2);

            if ($montoPrincipal > 0) {
                $esCompleto = abs($request->monto_pago - $montoTotal) < 0.01;
                $tipoPagoId = $esCompleto ? $idPagoFinal : $idAdelanto;

                Pago::create([
                    'reserva_id'       => $reserva->id,
                    'monto'            => $montoPrincipal,
                    'metodo_id'        => $request->metodo_id,
                    'tipo_id'          => $tipoPagoId,
                    'fecha_pago'       => now(),
                    'numero_operacion' => $numOp,
                ]);
            }

            $this->recalcularMontos($reserva);
        });

        return response()->json(['ok' => true]);
    }

    public function filtrar(Request $request)
    {
        $query = Reserva::with([
            'usuario', 'huespedes', 'habitaciones', 'estado', 'pagos.tipo'
        ])->orderByDesc('id');

        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->estado_id);
        }
        if ($request->filled('fecha_entrada') && !$request->boolean('inicial')) {
            $query->whereDate('fecha_entrada', $request->fecha_entrada);
        }

        if ($request->filled('habitacion')) {
            $query->whereHas('habitaciones', fn($q) =>
                $q->where('numero', $request->habitacion)
            );
        }
        if ($request->filled('huesped')) {
            $query->whereHas('huespedes', fn($q) =>
                $q->where('nombre', 'like', '%' . $request->huesped . '%')
                ->orWhere('num_doc', $request->huesped)
            );
        }

        if ($request->boolean('inicial')) {
            $hoy = now()->toDateString();
            $idActiva     = EstadoReserva::where('nombre', 'activa')->value('id');
            $idPendiente  = EstadoReserva::where('nombre', 'pendiente')->value('id');
            $idFinalizada = EstadoReserva::where('nombre', 'finalizada')->value('id');
            $idCancelada  = EstadoReserva::where('nombre', 'cancelada')->value('id');

            $query->where(function ($q) use ($hoy, $idActiva, $idPendiente, $idFinalizada, $idCancelada) {
                $q->where('estado_id', $idActiva)
                ->orWhere(function ($q2) use ($hoy, $idPendiente) {
                    $q2->where('estado_id', $idPendiente)
                        ->whereDate('fecha_entrada', $hoy);
                })
                ->orWhereDate('created_at', $hoy)
                ->orWhere(function ($q3) use ($hoy, $idFinalizada, $idCancelada) {
                    $q3->whereIn('estado_id', [$idFinalizada, $idCancelada])
                        ->whereDate('updated_at', $hoy);
                });
            });
        }

        $porPagina = 6;
        $pagina    = (int) $request->get('pagina', 1);

        $total = $query->count();
        $items = $query->skip(($pagina - 1) * $porPagina)->take($porPagina)->get();

        return response()->json([
            'data' => $items->map(function ($r) {
                $estado      = $r->estado->nombre;
                $montoPagado = $r->pagos->sum('monto');
                $saldo       = (float) $r->saldo_pendiente;

                return [
                    'id'                  => $r->id,
                    'tipo_estadia'        => $r->es_por_horas ? 'Horas' : 'Noches',
                    'fecha_entrada'       => $r->fecha_entrada->format('d/m/Y H:i'),
                    'fecha_salida'        => $r->fecha_salida->format('d/m/Y H:i'),
                    'estado_nombre'       => $estado,
                    'usuario'             => $r->usuario->name,
                    'huesped_principal'   => $r->huesped_principal,
                    'huespedes_extra'     => max(0, $r->huespedes->count() - 1),
                    'habitaciones'        => $r->habitaciones->pluck('numero')->join(', '),
                    'monto_total'         => (float) $r->costo_total,
                    'monto_pagado'        => $montoPagado,
                    'saldo_pendiente'     => $saldo,
                    'puede_checkin'       => $estado === 'pendiente' && $saldo <= 0,
                    'puede_editar_fechas' => $estado === 'pendiente',
                    'puede_reasignar'     => $estado === 'pendiente',
                    'puede_pago'          => $estado === 'pendiente' && $saldo > 0,
                    'puede_huespedes'     => in_array($estado, ['pendiente', 'activa']),
                    'puede_extension'     => $estado === 'activa',
                    'puede_finalizar'     => $estado === 'activa',
                    'puede_cancelar'      => $estado === 'pendiente',
                ];
            }),
            'total'         => $total,
            'por_pagina'    => $porPagina,
            'pagina_actual' => $pagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    }

    // ── ACCIÓN: Ver detalle ──
    public function show(Reserva $reserva)
    {
        $reserva->load([
            'estado',
            'usuario',
            'huespedes',
            'habitaciones.tipo',
            'pagos.metodo',
            'pagos.tipo',
            'extensiones.habitaciones',
            'extensiones.habitaciones',
            'devoluciones',
            'comprobante.tipo',
        ]);

        $montoPagado = $reserva->pagos->sum('monto');
        $ajusteDevoluciones = $reserva->devoluciones->sum(fn($d) => $d->monto_devuelto + $d->monto_retenido);
        $montoPagadoNeto = round($montoPagado - $ajusteDevoluciones, 2);

        $costoTotal  = (float) $reserva->costo_total;
        $saldo       = (float) $reserva->saldo_pendiente;

        $pagosExtension = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'extension')
            ->sortBy('id')
            ->values();

        $extensionesOrdenadas = $reserva->extensiones->sortBy('id')->values();

        return response()->json([
            'id'             => $reserva->id,
            'tipo_estadia'   => $reserva->es_por_horas ? 'Horas' : 'Noches',
            'huesped_principal' => $reserva->huesped_principal,
            'fecha_entrada'  => $reserva->fecha_entrada->format('d/m/Y H:i'),
            'fecha_salida'   => $reserva->fecha_salida->format('d/m/Y H:i'),
            'estado'         => $reserva->estado->nombre,
            'observacion'    => $reserva->observacion ?? '—',
            'registrado_por' => $reserva->usuario->name,
            'created_at'     => $reserva->created_at->format('d/m/Y H:i'),

            'habitaciones' => $reserva->habitaciones->map(fn($h) => [
                'numero'          => $h->numero,
                'tipo'            => $h->pivot->tipo_nombre_historico,
                'precio_aplicado' => number_format($h->pivot->precio_aplicado, 2),
                'tiempo_estadia'  => $h->pivot->tiempo_estadia,
                'fecha_salida'    => \Carbon\Carbon::parse($h->pivot->fecha_salida_efectiva)->format('d/m/Y H:i'),
            ]),

            'huespedes' => $reserva->huespedes->map(fn($h) => [
                'nombre'   => $h->nombre,
                'num_doc'  => $h->num_doc,
                'telefono' => $h->telefono ?? '—',
            ]),

            'pagos' => $reserva->pagos->map(fn($p) => [
                'id'          => $p->id,
                'monto'       => number_format($p->monto, 2),
                'tipo'        => $p->tipo->nombre,
                'metodo'      => ucfirst($p->metodo->nombre),
                'numero_operacion' => $p->numero_operacion,
                'fecha'       => $p->created_at->format('d/m/Y H:i'),
            ]),

            'extensiones' => $extensionesOrdenadas->map(function ($e, $index) use ($pagosExtension) {
                $pago = $pagosExtension->get($index);
                return [
                    'id'           => $e->id,
                    'cantidad'     => $e->cantidad,
                    'fecha'        => $e->created_at->format('d/m/Y H:i'),
                    'habitaciones' => $e->habitaciones->map(fn($h) => [
                        'numero' => $h->numero,
                        'monto'  => number_format($h->pivot->monto, 2),
                    ]),
                    'pago' => $pago ? [
                        'monto'  => number_format($pago->monto, 2),
                        'metodo' => ucfirst($pago->metodo->nombre),
                    ] : null,
                ];
            }),

            'devoluciones' => $reserva->devoluciones->map(fn($d) => [
                'origen'           => $d->origen,
                'monto_devuelto'   => number_format($d->monto_devuelto, 2),
                'monto_retenido'   => number_format($d->monto_retenido, 2),
                'metodo'           => ucfirst($d->metodo),
                'numero_operacion' => $d->numero_operacion,
                'fecha'            => \Carbon\Carbon::parse($d->fecha_devolucion)->format('d/m/Y H:i'),
            ]),

            'comprobante' => $reserva->comprobante ? [
                'serie'        => $reserva->comprobante->serie,
                'numero'       => $reserva->comprobante->numero,
                'tipo'         => $reserva->comprobante->tipo->nombre,
                'ruc'          => $reserva->comprobante->ruc,
                'razon_social' => $reserva->comprobante->razon_social,
            ] : null,

            'monto_total'  => number_format($costoTotal, 2),
            'monto_pagado' => number_format($montoPagadoNeto, 2),
            'saldo'        => number_format($saldo, 2),
        ]);
    }

    // Genera el PDF del comprobante asociado a la reserva
    public function comprobantePdf(Reserva $reserva)
    {
        $reserva->load([
            'comprobante.tipo',
            'huespedes',
            'habitaciones.tipo',
            'usuario',
            'extensiones.habitaciones',
        ]);

        if (!$reserva->comprobante) {
            abort(404, 'Esta reserva no tiene un comprobante asociado.');
        }

        $huespedPrincipal = $reserva->huespedes->firstWhere('num_doc', $reserva->huesped_principal);

        $datosVista = [
            'comprobante'      => $reserva->comprobante,
            'reserva'          => $reserva,
            'huespedPrincipal' => $huespedPrincipal,
        ];

        $anchoMm     = 80;
        $puntosPorMm = 2.8346;
        $altoMm      = 120;

        $maxIntentos = 6;
        $pdf         = null;

        for ($i = 0; $i < $maxIntentos; $i++) {
            $pdf = Pdf::loadView('comprobantes.pdf', $datosVista)
                ->setPaper([0, 0, $anchoMm * $puntosPorMm, $altoMm * $puntosPorMm]);

            $pdf->render();
            $totalPaginas = $pdf->getDomPDF()->getCanvas()->get_page_count();

            if ($totalPaginas <= 1) {
                break;
            }

            $altoMm += 40;
        }

        $nombreArchivo = "{$reserva->comprobante->serie}-{$reserva->comprobante->numero}.pdf";

        return $pdf->stream($nombreArchivo);
    }

    // ── ACCIÓN: Registrar pago ──
    public function registrarPago(Request $request, Reserva $reserva)
    {
        $request->validate([
            'monto'            => 'required|numeric|min:0.01',
            'metodo_id'        => 'required|exists:metodos_pago,id',
            'numero_operacion' => 'nullable|string|max:30',
        ]);

        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json([
                'error' => 'Solo se puede registrar pago en reservas pendientes.'
            ], 422);
        }

        $metodo = MetodoPago::findOrFail($request->metodo_id);
        if ($metodo->nombre !== 'efectivo' && !$request->filled('numero_operacion')) {
            return response()->json([
                'error' => 'El número de operación es obligatorio para este método de pago.'
            ], 422);
        }

        $saldo = (float) $reserva->saldo_pendiente;

        if ($saldo <= 0) {
            return response()->json([
                'error' => 'Esta reserva ya está pagada al 100%.'
            ], 422);
        }

        $monto = round((float) $request->monto, 2);

        if ($monto > $saldo) {
            return response()->json([
                'error' => 'El monto ingresado (S/ ' . number_format($monto, 2) . ') supera el saldo pendiente (S/ ' . number_format($saldo, 2) . ').'
            ], 422);
        }

        $esFinal    = abs($monto - $saldo) < 0.01;
        $tipoPagoId = TipoPago::where('nombre', $esFinal ? 'pago final' : 'adelanto')->value('id');

        Pago::create([
            'reserva_id'       => $reserva->id,
            'monto'            => $monto,
            'metodo_id'        => $request->metodo_id,
            'tipo_id'          => $tipoPagoId,
            'fecha_pago'       => now(),
            'numero_operacion' => $request->numero_operacion,
        ]);

        $this->recalcularMontos($reserva);

        return response()->json(['ok' => true]);
    }

    // ── ACCIÓN: Editar fechas/tipo ──
    public function editarFechasInfo(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden editar reservas pendientes.'], 422);
        }

        $reserva->load(['habitaciones.tipo', 'devoluciones']);

        $idIngresoTemprano = TipoPago::where('nombre', 'ingreso temprano')->value('id');
        $recargoPagado = round(
            $reserva->pagos()->where('tipo_id', $idIngresoTemprano)->sum('monto'), 2
        );

        // Neto de devoluciones previas, consistente con recalcularMontos()
        $montoPagadoBruto   = $reserva->pagos()->sum('monto');
        $ajusteDevoluciones = $reserva->devoluciones->sum(fn($d) => $d->monto_devuelto + $d->monto_retenido);
        $montoPagadoNeto    = round($montoPagadoBruto - $ajusteDevoluciones, 2);

        return response()->json([
            'tipo_estadia'   => $reserva->es_por_horas ? 'horas' : 'noches',
            'fecha_entrada'  => $reserva->fecha_entrada->format('Y-m-d\TH:i'),
            'fecha_salida'   => $reserva->fecha_salida->format('Y-m-d\TH:i'),
            'observacion'    => $reserva->observacion ?? '',
            'monto_total'    => (float) $reserva->costo_total,
            'monto_pagado'   => $montoPagadoNeto,
            'recargo_pagado' => $recargoPagado,
            'habitaciones'   => $reserva->habitaciones->map(fn($h) => [
                'numero'           => $h->numero,
                'tipo_nombre'      => $h->tipo->nombre,
                'precio_hora_raw'  => (float) $h->tipo->precio_hora,
                'precio_noche_raw' => (float) $h->tipo->precio_noche,
            ]),
        ]);
    }

    public function editarFechasDisponibilidad(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden editar reservas pendientes.'], 422);
        }

        $request->validate([
            'fecha_entrada' => 'required|date',
            'fecha_salida'  => 'required|date|after:fecha_entrada',
        ]);

        $reserva->load('habitaciones');

        $conflictos = [];
        foreach ($reserva->habitaciones as $hab) {
            $conflicto = $this->queryConflictoHorario($request->fecha_entrada, $request->fecha_salida, $reserva->id)
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->exists();

            if ($conflicto) {
                $conflictos[] = $hab->numero;
            }
        }

        return response()->json([
            'disponible' => empty($conflictos),
            'conflictos' => $conflictos,
        ]);
    }

    public function editarFechas(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden editar reservas pendientes.'], 422);
        }

        $request->validate([
            'tipo_estadia'      => 'required|in:horas,noches',
            'fecha_entrada'     => 'required|date',
            'fecha_salida'      => 'required|date|after:fecha_entrada',
            'observacion'       => 'nullable|string|max:255',
            'franja'            => 'nullable|string',
            'monto_pago'        => 'nullable|numeric|min:0.01',
            'metodo_id'         => 'nullable|exists:metodos_pago,id',
            'numero_operacion'  => 'nullable|string|max:30',
            'credito_monto_devuelto'   => 'nullable|numeric|min:0',
            'credito_metodo_id'        => 'nullable|exists:metodos_pago,id',
            'credito_numero_operacion' => 'nullable|string|max:30',
        ]);

        $ahora   = new \DateTime();
        $entrada = new \DateTime($request->fecha_entrada);
        if ($entrada < $ahora) {
            return response()->json([
                'error' => 'La fecha de entrada no puede ser anterior a la fecha y hora actual.'
            ], 422);
        }

        $reserva->load(['habitaciones.tipo', 'devoluciones']);

        $salidaDt = new \DateTime($request->fecha_salida);
        foreach ($reserva->habitaciones as $hab) {
            $conflicto = $this->queryConflictoHorario($request->fecha_entrada, $request->fecha_salida, $reserva->id)
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->exists();

            if ($conflicto) {
                return response()->json([
                    'error' => "La habitación N°{$hab->numero} ya no está disponible en el nuevo rango de fechas. " .
                            "Use 'Reasignar Habitaciones' o elija otras fechas."
                ], 422);
            }
        }

        $tipoNombre = $request->tipo_estadia;
        $esPorHoras = $tipoNombre === 'horas';

        $entrada = new \DateTime($request->fecha_entrada);
        $salida  = new \DateTime($request->fecha_salida);

        $franja = $esPorHoras ? 'horas' : $this->detectarFranja($entrada);

        $nuevoMontoBase  = 0;
        $nuevoMontoEarly = 0;

        foreach ($reserva->habitaciones as $hab) {
            if ($esPorHoras) {
                $horas           = round(($salida->getTimestamp() - $entrada->getTimestamp()) / 3600);
                $nuevoMontoBase += $hab->tipo->precio_hora * $horas;
            } else {
                $entDia = new \DateTime($entrada->format('Y-m-d'));
                $salDia = new \DateTime($salida->format('Y-m-d'));
                $diff   = $entDia->diff($salDia)->days;
                $noches = $franja === 'madrugada'
                    ? ($diff === 0 ? 1 : $diff + 1)
                    : ($diff < 1 ? 1 : $diff);
                $nuevoMontoBase += $hab->tipo->precio_noche * $noches;
                if ($franja === 'early') {
                    $nuevoMontoEarly += $hab->tipo->precio_hora * 2;
                }
            }
        }

        $nuevoTotal = round($nuevoMontoBase + $nuevoMontoEarly, 2);

        $montoPagadoBruto           = round($reserva->pagos()->sum('monto'), 2);
        $ajusteDevolucionesPrevias  = $reserva->devoluciones->sum(fn($d) => $d->monto_devuelto + $d->monto_retenido);
        $montoPagado                = round($montoPagadoBruto - $ajusteDevolucionesPrevias, 2);

        $idIngresoTemprano   = TipoPago::where('nombre', 'ingreso temprano')->value('id');
        $recargoPagadoPrevio = round(
            $reserva->pagos()->where('tipo_id', $idIngresoTemprano)->sum('monto'), 2
        );

        $diferenciaRecargo = max(0, round($nuevoMontoEarly - $recargoPagadoPrevio, 2));

        $minimo50        = round($nuevoTotal * 0.5, 2);
        $diferencia      = round($nuevoTotal - $montoPagado, 2);
        $faltaParaMinimo = max(0, round($minimo50 - $montoPagado, 2));

        $minimoRequerido = max($faltaParaMinimo, $diferenciaRecargo);

        $esCasoA = $nuevoTotal > $montoPagado && $minimoRequerido > 0;

        if ($esCasoA) {
            if (! $request->filled('monto_pago') || ! $request->filled('metodo_id')) {
                return response()->json(['error' => 'Se requiere un pago adicional para cubrir el mínimo requerido.'], 422);
            }

            $montoPago       = round((float) $request->monto_pago, 2);
            $maximoPermitido = $diferencia;

            if ($montoPago < $minimoRequerido) {
                return response()->json([
                    'error' => "El pago mínimo requerido es S/ " . number_format($minimoRequerido, 2) .
                            ($diferenciaRecargo > 0 ? " (incluye recargo por ingreso temprano)." : ".")
                ], 422);
            }
            if ($montoPago > $maximoPermitido) {
                return response()->json([
                    'error' => "El pago no puede superar el saldo pendiente de S/ " . number_format($maximoPermitido, 2) . "."
                ], 422);
            }

            $metodoPago = MetodoPago::find($request->metodo_id);
            if ($metodoPago && $metodoPago->nombre !== 'efectivo' && !$request->filled('numero_operacion')) {
                return response()->json([
                    'error' => 'El número de operación es obligatorio para este método de pago.'
                ], 422);
            }
        }

        $esCasoC = $nuevoTotal < $montoPagado;

        if ($esCasoC) {
            $creditoTotal = round($montoPagado - $nuevoTotal, 2);

            if (! $request->filled('credito_monto_devuelto')) {
                return response()->json(['error' => 'Debe indicar cuánto se devolverá del crédito a favor (puede ser 0).'], 422);
            }

            $montoDevuelto = round((float) $request->credito_monto_devuelto, 2);

            if ($montoDevuelto < 0 || $montoDevuelto > $creditoTotal) {
                return response()->json([
                    'error' => "El monto a devolver debe estar entre S/ 0.00 y S/ " . number_format($creditoTotal, 2) . "."
                ], 422);
            }

            if ($montoDevuelto > 0 && ! $request->filled('credito_metodo_id')) {
                return response()->json(['error' => 'Seleccione un método de devolución.'], 422);
            }

            if ($montoDevuelto > 0) {
                $metodoCredito = MetodoPago::find($request->credito_metodo_id);
                if ($metodoCredito && $metodoCredito->nombre !== 'efectivo' && !$request->filled('credito_numero_operacion')) {
                    return response()->json([
                        'error' => 'El número de operación es obligatorio para este método de devolución.'
                    ], 422);
                }
            }
        }

        DB::transaction(function () use (
            $request, $reserva, $esPorHoras, $franja,
            $nuevoTotal, $montoPagado, $esCasoA, $esCasoC, $diferenciaRecargo, $nuevoMontoEarly
        ) {
            $entrada = new \DateTime($request->fecha_entrada);
            $salida  = new \DateTime($request->fecha_salida);

            $idReservadaHab  = EstadoHabitacion::where('nombre', 'reservada')->value('id');
            $idDisponibleHab = EstadoHabitacion::where('nombre', 'disponible')->value('id');
            foreach ($reserva->habitaciones as $habAntigua) {
                if ($habAntigua->estado_id === $idReservadaHab) {
                    $habAntigua->update(['estado_id' => $idDisponibleHab]);
                }
            }

            $reserva->update([
                'es_por_horas' => $esPorHoras,
                'fecha_entrada' => $request->fecha_entrada,
                'fecha_salida'  => $request->fecha_salida,
                'observacion'   => $request->observacion,
                'monto_recargo' => $nuevoMontoEarly,
            ]);

            foreach ($reserva->habitaciones as $hab) {
                if ($esPorHoras) {
                    $horas  = round(($salida->getTimestamp() - $entrada->getTimestamp()) / 3600);
                    $precio = $hab->tipo->precio_hora * $horas;
                    $reserva->habitaciones()->updateExistingPivot($hab->numero, [
                        'precio_aplicado' => $precio,
                        'tiempo_estadia'  => $horas,
                        'fecha_salida_efectiva' => $request->fecha_salida,
                    ]);
                } else {
                    $entDia = new \DateTime($entrada->format('Y-m-d'));
                    $salDia = new \DateTime($salida->format('Y-m-d'));
                    $diff   = $entDia->diff($salDia)->days;
                    $noches = $franja === 'madrugada'
                        ? ($diff === 0 ? 1 : $diff + 1)
                        : ($diff < 1 ? 1 : $diff);
                    $precio = $hab->tipo->precio_noche * $noches;
                    $reserva->habitaciones()->updateExistingPivot($hab->numero, [
                        'precio_aplicado' => $precio,
                        'tiempo_estadia'  => $noches,
                        'fecha_salida_efectiva' => $request->fecha_salida,
                    ]);
                }
            }

            if ($esCasoA) {
                $montoPago = round((float) $request->monto_pago, 2);

                $montoParaRecargo = min($montoPago, $diferenciaRecargo);
                if ($montoParaRecargo > 0) {
                    $idIngresoTemprano = TipoPago::where('nombre', 'ingreso temprano')->value('id');
                    Pago::create([
                        'reserva_id'       => $reserva->id,
                        'monto'            => $montoParaRecargo,
                        'metodo_id'        => $request->metodo_id,
                        'tipo_id'          => $idIngresoTemprano,
                        'fecha_pago'       => now(),
                        'numero_operacion' => $request->numero_operacion,
                    ]);
                }

                $montoRestante = round($montoPago - $montoParaRecargo, 2);
                if ($montoRestante > 0) {
                    $esFinal     = abs(($montoPagado + $montoPago) - $nuevoTotal) < 0.01;
                    $idAdelanto  = TipoPago::where('nombre', 'adelanto')->value('id');
                    $idPagoFinal = TipoPago::where('nombre', 'pago final')->value('id');

                    Pago::create([
                        'reserva_id'       => $reserva->id,
                        'monto'            => $montoRestante,
                        'metodo_id'        => $request->metodo_id,
                        'tipo_id'          => $esFinal ? $idPagoFinal : $idAdelanto,
                        'fecha_pago'       => now(),
                        'numero_operacion' => $request->numero_operacion,
                    ]);
                }
            }

            if ($esCasoC) {
                $creditoTotal   = round($montoPagado - $nuevoTotal, 2);
                $montoDevuelto  = round((float) $request->credito_monto_devuelto, 2);
                $montoRetenido  = round($creditoTotal - $montoDevuelto, 2);

                $metodoNombre    = 'efectivo';
                $numeroOperacion = null;

                if ($montoDevuelto > 0) {
                    $metodoObj    = MetodoPago::find($request->credito_metodo_id);
                    $metodoNombre = $metodoObj->nombre;
                    if ($metodoNombre !== 'efectivo') {
                        $numeroOperacion = $request->credito_numero_operacion;
                    }
                }

                Devolucion::updateOrCreate(
                    ['reserva_id' => $reserva->id, 'origen' => 'ajuste fechas'],
                    [
                        'monto_devuelto'   => $montoDevuelto,
                        'monto_retenido'   => $montoRetenido,
                        'metodo'           => $metodoNombre,
                        'numero_operacion' => $numeroOperacion,
                        'fecha_devolucion' => now(),
                    ]
                );
            }
            $this->recalcularMontos($reserva);
        });

        return response()->json(['ok' => true]);
    }

    // ── ACCIÓN: Reasignar habitaciones ──
    public function reasignarInfo(Reserva $reserva): JsonResponse
    {
        if (! in_array($reserva->estado->nombre, ['pendiente', 'activa'])) {
            return response()->json(['error' => 'Solo se pueden reasignar habitaciones en reservas pendientes.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

        $idsNoAptos = EstadoHabitacion::whereIn('nombre', ['limpieza', 'mantenimiento'])->pluck('id');

        $ocupadasEnRango = $this->queryConflictoHorario($reserva->fecha_entrada, $reserva->fecha_salida, $reserva->id)
            ->pluck('reserva_habitaciones.habitacion_numero')
            ->toArray();
        $enEstaReserva = $reserva->habitaciones->pluck('numero')->toArray();

        $habitaciones = $reserva->habitaciones->map(function ($hab) use ($reserva, $idsNoAptos, $ocupadasEnRango, $enEstaReserva) {
            $alternativas = Habitacion::with('tipo')
                ->where('tipo_id', $hab->tipo_id)
                ->where('activo', 1)
                ->whereNotIn('estado_id', $idsNoAptos)
                ->where('numero', '!=', $hab->numero)
                ->whereNotIn('numero', $ocupadasEnRango)
                ->whereNotIn('numero', $enEstaReserva)
                ->orderBy('numero')
                ->get()
                ->map(fn($a) => [
                    'numero'      => $a->numero,
                    'tipo_nombre' => $a->tipo->nombre,
                ]);

            return [
                'numero'          => $hab->numero,
                'tipo_id'         => $hab->tipo_id,
                'tipo_nombre'     => $hab->pivot->tipo_nombre_historico,
                'precio_aplicado' => number_format($hab->pivot->precio_aplicado, 2),
                'tiempo_estadia'  => $hab->pivot->tiempo_estadia,
                'alternativas'    => $alternativas,
            ];
        });

        return response()->json([
            'habitaciones'  => $habitaciones,
            'tipo_estadia'  => $reserva->es_por_horas ? 'horas' : 'noches',
            'fecha_entrada' => $reserva->fecha_entrada->format('d/m/Y H:i'),
            'fecha_salida'  => $reserva->fecha_salida->format('d/m/Y H:i'),
        ]);
    }

    public function reasignar(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden reasignar habitaciones en reservas pendientes.'], 422);
        }

        $request->validate([
            'cambios'      => 'required|array|min:1',
            'cambios.*.de' => 'required|integer|exists:habitaciones,numero',
            'cambios.*.a'  => 'required|integer|exists:habitaciones,numero|different:cambios.*.de',
        ]);

        $reserva->load(['habitaciones.tipo']);

        $idsNoAptos = EstadoHabitacion::whereIn('nombre', ['limpieza', 'mantenimiento'])->pluck('id');

        foreach ($request->cambios as $cambio) {
            $de = $cambio['de'];
            $a  = $cambio['a'];

            $habActual = $reserva->habitaciones->firstWhere('numero', $de);
            if (!$habActual) {
                return response()->json([
                    'error' => "La habitación N°{$de} no pertenece a esta reserva."
                ], 422);
            }

            $habNueva = Habitacion::with('tipo')->where('numero', $a)->first();
            if (!$habNueva || $habNueva->tipo_id !== $habActual->tipo_id) {
                return response()->json([
                    'error' => "La habitación N°{$a} no es del mismo tipo que la N°{$de}."
                ], 422);
            }

            $conflicto = $this->queryConflictoHorario($reserva->fecha_entrada, $reserva->fecha_salida, $reserva->id)
                ->where('reserva_habitaciones.habitacion_numero', $a)
                ->exists();

            if ($conflicto) {
                return response()->json([
                    'error' => "La habitación N°{$a} no está disponible en el rango de la reserva."
                ], 422);
            }

            if ($idsNoAptos->contains($habNueva->estado_id)) {
                return response()->json([
                    'error' => "La habitación N°{$a} está en limpieza o mantenimiento."
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $reserva) {
            $idReservadaHab  = EstadoHabitacion::where('nombre', 'reservada')->value('id');
            $idOcupadaHab    = EstadoHabitacion::where('nombre', 'ocupada')->value('id');
            $idDisponibleHab = EstadoHabitacion::where('nombre', 'disponible')->value('id');

            foreach ($request->cambios as $cambio) {
                $de = $cambio['de'];
                $a  = $cambio['a'];

                $habActual = $reserva->habitaciones->firstWhere('numero', $de);

                $pivotData = [
                    'precio_aplicado' => $habActual->pivot->precio_aplicado,
                    'tiempo_estadia'  => $habActual->pivot->tiempo_estadia,
                    'fecha_salida_efectiva' => $habActual->pivot->fecha_salida_efectiva,
                    'tipo_nombre_historico' => $habActual->pivot->tipo_nombre_historico,
                ];

                // Guardamos el estado físico ANTES de tocar nada, para replicarlo en la nueva habitación
                $habAnteriorObj  = Habitacion::where('numero', $de)->first();
                $estadoAReplicar = $habAnteriorObj?->estado_id;

                $reserva->habitaciones()->detach($de);
                $reserva->habitaciones()->attach($a, $pivotData);

                // Liberamos la habitación anterior si estaba tomada por ESTA reserva (reservada u ocupada)
                if ($habAnteriorObj && in_array($habAnteriorObj->estado_id, [$idReservadaHab, $idOcupadaHab])) {
                    $habAnteriorObj->update(['estado_id' => $idDisponibleHab]);
                }

                // Replicamos el mismo estado en la nueva habitación (si la reserva estaba activa, la nueva pasa a ocupada; etc.)
                $habNuevaObj = Habitacion::where('numero', $a)->first();
                if ($habNuevaObj && in_array($estadoAReplicar, [$idReservadaHab, $idOcupadaHab])) {
                    $habNuevaObj->update(['estado_id' => $estadoAReplicar]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    // ── ACCIÓN: Editar huéspedes ──
    public function huespedInfo(Reserva $reserva): JsonResponse
    {
        $estadosPermitidos = ['pendiente', 'activa'];
        if (! in_array($reserva->estado->nombre, $estadosPermitidos)) {
            return response()->json(['error' => 'No se puede editar huéspedes en este estado.'], 422);
        }

        $reserva->load(['huespedes', 'habitaciones.tipo']);

        $huespedes = $reserva->huespedes->map(fn($h) => [
            'num_doc'  => $h->num_doc,
            'nombre'   => $h->nombre,
            'telefono' => $h->telefono ?? '—',
        ]);

        $maxPermitido = $reserva->habitaciones->sum(fn($h) => $h->tipo->max_huespedes);

        return response()->json([
            'huespedes'         => $huespedes,
            'huesped_principal' => $reserva->huesped_principal,
            'max_permitido'     => $maxPermitido,
            'estado'            => $reserva->estado->nombre,
        ]);
    }

    public function editarHuespedes(Request $request, Reserva $reserva): JsonResponse
    {
        $estadosPermitidos = ['pendiente', 'activa'];
        if (! in_array($reserva->estado->nombre, $estadosPermitidos)) {
            return response()->json(['error' => 'No se puede editar huéspedes en este estado.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

        $request->validate([
            'huespedes'         => ['required', 'array', 'min:1'],
            'huespedes.*'       => ['string', 'exists:huespedes,num_doc'],
            'huesped_principal' => ['required', 'string', 'exists:huespedes,num_doc'],
        ]);

        $numsDoc = $request->huespedes;

        if (! in_array($request->huesped_principal, $numsDoc)) {
            return response()->json([
                'error' => 'El huésped principal debe estar dentro de la lista de huéspedes.'
            ], 422);
        }

        $maxPermitido = $reserva->habitaciones->sum(fn($h) => $h->tipo->max_huespedes);
        if (count($numsDoc) > $maxPermitido) {
            return response()->json([
                'error' => "Máximo permitido: {$maxPermitido} huésped(es) para las habitaciones de esta reserva.",
            ], 422);
        }

        $reserva->huespedes()->sync($numsDoc);
        $reserva->update(['huesped_principal' => $request->huesped_principal]);

        return response()->json(['ok' => true]);
    }

    // ── ACCIÓN: Check-in ──
    public function checkinInfo(Reserva $reserva)
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'La reserva no está en estado pendiente.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

        $saldo = (float) $reserva->saldo_pendiente;

        if ($saldo > 0) {
            return response()->json(['error' => 'La reserva tiene saldo pendiente de S/ ' . number_format($saldo, 2) . '. Registre el pago antes de hacer check-in.'], 422);
        }

        $ahora       = now();
        $horaActual  = $ahora->hour * 60 + $ahora->minute;
        $EARLY_FIN   = 11 * 60;
        $esPorHoras  = $reserva->es_por_horas;

        $nuevaSalida = null;

        if ($esPorHoras) {
            $horas  = $reserva->habitaciones->first()->pivot->tiempo_estadia ?? 2;
            $salida = $ahora->copy()->addHours($horas);
            $mins   = $salida->minute;
            if ($mins % 10 !== 0) {
                $redondeado = (int) ceil($mins / 10) * 10;
                if ($redondeado === 60) {
                    $salida->addHour()->setMinute(0)->setSecond(0);
                } else {
                    $salida->setMinute($redondeado)->setSecond(0);
                }
            }
            $nuevaSalida = $salida;
        } else {
            $nochesPagadas = $reserva->habitaciones->first()->pivot->tiempo_estadia ?? 1;
            $nuevaSalida   = $ahora->copy()->addDays($nochesPagadas)->setHour(11)->setMinute(0)->setSecond(0);

            if ($horaActual <= 6 * 60) {
                $nuevaSalida = $ahora->copy()->setHour(11)->setMinute(0)->setSecond(0);
                if ($nochesPagadas > 1) {
                    $nuevaSalida->addDays($nochesPagadas - 1);
                }
            }
        }

        $idsNoAptos = EstadoHabitacion::whereIn('nombre', ['limpieza', 'mantenimiento'])
            ->pluck('nombre', 'id');

        $habitacionesInfo = [];
        $todasLibres      = true;

        foreach ($reserva->habitaciones as $hab) {
            if ($idsNoAptos->has($hab->estado_id)) {
                $todasLibres = false;
                $habitacionesInfo[] = [
                    'numero'         => $hab->numero,
                    'disponible'     => false,
                    'motivo'         => 'estado',
                    'estado_actual'  => $idsNoAptos->get($hab->estado_id),
                ];
                continue;
            }

            $conflicto = $this->queryConflictoHorario($ahora, $nuevaSalida, $reserva->id)
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->select('reserva_habitaciones.fecha_salida_efectiva', 'reservas.es_por_horas')
                ->orderBy('reservas.fecha_entrada')
                ->first();

            if ($conflicto) {
                $todasLibres = false;
                $fechaSalida = \Carbon\Carbon::parse($conflicto->fecha_salida_efectiva);
                $disponibleA = $fechaSalida->format('d/m') . ' ' . ($conflicto->es_por_horas
                    ? $fechaSalida->format('H:i')
                    : '11:00 AM');

                $habitacionesInfo[] = [
                    'numero'       => $hab->numero,
                    'disponible'   => false,
                    'motivo'       => 'conflicto',
                    'disponible_a' => $disponibleA,
                ];
            } else {
                $habitacionesInfo[] = [
                    'numero'     => $hab->numero,
                    'disponible' => true,
                ];
            }
        }

        $hayRecargo   = false;
        $recargo      = 0;
        $esAnticipada = $ahora->lt($reserva->fecha_entrada);

        if ($todasLibres && !$esPorHoras) {
            $entradaOriginalHora = $reserva->fecha_entrada->hour * 60 + $reserva->fecha_entrada->minute;
            if ($horaActual < $EARLY_FIN && $entradaOriginalHora > $EARLY_FIN) {
                $hayRecargo = true;
                foreach ($reserva->habitaciones as $hab) {
                    $recargo += $hab->tipo->precio_hora * 2;
                }
            }
        }

        return response()->json([
            'todas_libres'     => $todasLibres,
            'habitaciones'     => $habitacionesInfo,
            'es_por_horas'     => $esPorHoras,
            'es_anticipada'    => $esAnticipada,
            'nueva_salida'     => $nuevaSalida ? $nuevaSalida->format('d/m/Y H:i') : null,
            'hay_recargo'      => $hayRecargo,
            'recargo'          => round($recargo, 2),
            'hora_actual'      => $ahora->format('H:i'),
            'entrada_original' => $reserva->fecha_entrada->format('d/m/Y H:i'),
        ]);
    }

    public function checkin(Request $request, Reserva $reserva)
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'La reserva no está en estado pendiente.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

        $saldo = (float) $reserva->saldo_pendiente;

        if ($saldo > 0) {
            return response()->json(['error' => 'La reserva tiene saldo pendiente.'], 422);
        }

        $idsNoAptos  = EstadoHabitacion::whereIn('nombre', ['limpieza', 'mantenimiento'])->pluck('id');
        $habsNoAptas = $reserva->habitaciones->filter(fn($h) => $idsNoAptos->contains($h->estado_id));

        if ($habsNoAptas->isNotEmpty()) {
            $numeros = $habsNoAptas->pluck('numero')->join(', ');
            return response()->json([
                'error' => "La(s) habitación(es) N° {$numeros} está(n) en limpieza o mantenimiento. Márquela(s) como disponible antes de hacer check-in."
            ], 422);
        }
        
        $ahora      = now();
        $horaActual = $ahora->hour * 60 + $ahora->minute;
        $EARLY_FIN  = 11 * 60;
        $esPorHoras = $reserva->es_por_horas;

        $nuevaSalida = null;

        if ($esPorHoras) {
            $horas  = $reserva->habitaciones->first()->pivot->tiempo_estadia ?? 2;
            $salida = $ahora->copy()->addHours($horas);
            $mins   = $salida->minute;
            if ($mins % 10 !== 0) {
                $redondeado = (int) ceil($mins / 10) * 10;
                if ($redondeado === 60) {
                    $salida->addHour()->setMinute(0)->setSecond(0);
                } else {
                    $salida->setMinute($redondeado)->setSecond(0);
                }
            }
            $nuevaSalida = $salida;
        } else {
            $nochesPagadas = $reserva->habitaciones->first()->pivot->tiempo_estadia ?? 1;
            $nuevaSalida   = $ahora->copy()->addDays($nochesPagadas)->setHour(11)->setMinute(0)->setSecond(0);

            if ($horaActual <= 6 * 60) {
                $nuevaSalida = $ahora->copy()->setHour(11)->setMinute(0)->setSecond(0);
                if ($nochesPagadas > 1) {
                    $nuevaSalida->addDays($nochesPagadas - 1);
                }
            }
        }

        // Revalidación server-side de disponibilidad (antes solo se validaba en checkinInfo())
        foreach ($reserva->habitaciones as $hab) {
            $conflicto = $this->queryConflictoHorario($ahora, $nuevaSalida, $reserva->id)
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->exists();

            if ($conflicto) {
                return response()->json([
                    'error' => "La habitación N°{$hab->numero} ya no está disponible. Actualice la información e intente nuevamente."
                ], 422);
            }
        }

        $hayRecargo = false;
        $recargo    = 0;

        if (!$esPorHoras) {
            $entradaOriginalHora = $reserva->fecha_entrada->hour * 60 + $reserva->fecha_entrada->minute;
            if ($horaActual < $EARLY_FIN && $entradaOriginalHora > $EARLY_FIN) {
                $hayRecargo = true;
                foreach ($reserva->habitaciones as $hab) {
                    $recargo += $hab->tipo->precio_hora * 2;
                }
            }
        }

        if ($hayRecargo) {
            $request->validate([
                'metodo_id'        => 'required|exists:metodos_pago,id',
                'numero_operacion' => 'nullable|string|max:30',
            ]);

            $metodoPago = MetodoPago::find($request->metodo_id);
            if ($metodoPago && $metodoPago->nombre !== 'efectivo' && !$request->filled('numero_operacion')) {
                return response()->json([
                    'error' => 'El número de operación es obligatorio para este método de pago.'
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $reserva, $ahora, $nuevaSalida, $hayRecargo, $recargo) {
            if ($hayRecargo && $recargo > 0) {
                $reserva->monto_recargo = $recargo;

                $idIngresoTemprano = TipoPago::where('nombre', 'ingreso temprano')->value('id');
                Pago::create([
                    'reserva_id'       => $reserva->id,
                    'monto'            => $recargo,
                    'metodo_id'        => $request->metodo_id,
                    'tipo_id'          => $idIngresoTemprano,
                    'fecha_pago'       => now(),
                    'numero_operacion' => $request->numero_operacion,
                ]);
            }

            $reserva->fecha_entrada = $ahora;
            $reserva->fecha_salida  = $nuevaSalida;

            $idActiva = EstadoReserva::where('nombre', 'activa')->value('id');
            $reserva->estado_id = $idActiva;
            $reserva->save();

            foreach ($reserva->habitaciones as $hab) {
                $reserva->habitaciones()->updateExistingPivot($hab->numero, [
                    'fecha_salida_efectiva' => $nuevaSalida,
                ]);
            }

            $idOcupada = EstadoHabitacion::where('nombre', 'ocupada')->value('id');
            foreach ($reserva->habitaciones as $hab) {
                $hab->update(['estado_id' => $idOcupada]);
            }

            $this->recalcularMontos($reserva);
        });

        return response()->json(['ok' => true]);
    }

    // ── ACCIÓN: Agregar extensión ──
    public function extensionInfo(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'activa') {
            return response()->json(['error' => 'Solo se pueden extender reservas activas.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

        $esPorHoras = $reserva->es_por_horas;
        $cantidad   = (int) request('cantidad', 1);

        if ($cantidad < 1) {
            return response()->json(['error' => 'La cantidad mínima es 1.'], 422);
        }

        $habitaciones = [];

        foreach ($reserva->habitaciones as $hab) {
            $salidaActualHab = \Carbon\Carbon::parse($hab->pivot->fecha_salida_efectiva);

            $nuevaSalidaHab = $esPorHoras
                ? $salidaActualHab->copy()->addHours($cantidad)
                : $salidaActualHab->copy()->addDays($cantidad)->setHour(11)->setMinute(0)->setSecond(0);

            $conflicto = $this->queryConflictoHorario($salidaActualHab, $nuevaSalidaHab, $reserva->id)
                ->join('estados_reserva', 'estados_reserva.id', '=', 'reservas.estado_id')
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->select('reservas.id as reserva_id', 'estados_reserva.nombre as estado_reserva')
                ->orderBy('reservas.fecha_entrada')
                ->first();

            $montoExtension = $esPorHoras
                ? round($hab->tipo->precio_hora  * $cantidad, 2)
                : round($hab->tipo->precio_noche * $cantidad, 2);

            $habitaciones[] = [
                'numero'        => $hab->numero,
                'tipo'          => $hab->tipo->nombre,
                'disponible'    => !$conflicto,
                'salida_actual' => $salidaActualHab->format('d/m/Y H:i'),
                'nueva_salida'  => $nuevaSalidaHab->format('d/m/Y H:i'),
                'monto'         => $montoExtension,
                'reserva_id'       => $conflicto->reserva_id ?? null,
                'estado_conflicto' => $conflicto->estado_reserva ?? null,
            ];
        }

        $disponibles = array_filter($habitaciones, fn($h) => $h['disponible']);
        $montoTotal  = round(array_sum(array_column(array_values($disponibles), 'monto')), 2);
        $unidadLabel = $esPorHoras
            ? ($cantidad === 1 ? '1 hora' : "{$cantidad} horas")
            : ($cantidad === 1 ? '1 noche' : "{$cantidad} noches");

        return response()->json([
            'tipo_estadia'    => $esPorHoras ? 'horas' : 'noches',
            'es_por_horas'    => $esPorHoras,
            'unidad_label'    => $unidadLabel,
            'habitaciones'    => array_values($habitaciones),
            'monto_total'     => $montoTotal,
            'hay_disponibles' => count($disponibles) > 0,
        ]);
    }

    public function agregarExtension(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'activa') {
            return response()->json(['error' => 'Solo se pueden extender reservas activas.'], 422);
        }

        $request->validate([
            'cantidad'         => 'required|integer|min:1',
            'metodo_id'        => 'required|exists:metodos_pago,id',
            'habitaciones'     => 'required|array|min:1',
            'habitaciones.*'   => 'integer',
            'monto'            => 'required|numeric|min:0.01',
            'numero_operacion' => 'nullable|string|max:30',
        ]);

        $metodo = MetodoPago::findOrFail($request->metodo_id);
        if ($metodo->nombre !== 'efectivo' && !$request->filled('numero_operacion')) {
            return response()->json(['error' => 'El número de operación es obligatorio para este método de pago.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

        $esPorHoras       = $reserva->es_por_horas;
        $cantidad         = (int) $request->cantidad;
        $numerosEnviados  = $request->habitaciones;
        $habitacionesValidas = [];

        foreach ($reserva->habitaciones as $hab) {
            if (!in_array($hab->numero, $numerosEnviados)) continue;

            $salidaActualHab = \Carbon\Carbon::parse($hab->pivot->fecha_salida_efectiva);
            $nuevaSalidaHab  = $esPorHoras
                ? $salidaActualHab->copy()->addHours($cantidad)
                : $salidaActualHab->copy()->addDays($cantidad)->setHour(11)->setMinute(0)->setSecond(0);

            $conflicto = $this->queryConflictoHorario($salidaActualHab, $nuevaSalidaHab, $reserva->id)
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->exists();

            if (!$conflicto) {
                $monto = $esPorHoras
                    ? round($hab->tipo->precio_hora  * $cantidad, 2)
                    : round($hab->tipo->precio_noche * $cantidad, 2);

                $habitacionesValidas[] = [
                    'numero'       => $hab->numero,
                    'monto'        => $monto,
                    'nueva_salida' => $nuevaSalidaHab,
                ];
            }
        }

        if (empty($habitacionesValidas)) {
            return response()->json(['error' => 'Ninguna de las habitaciones seleccionadas está disponible para extender.'], 422);
        }

        $montoCalculado = round(array_sum(array_column($habitacionesValidas, 'monto')), 2);
        $montoEnviado   = round((float) $request->monto, 2);

        if (abs($montoCalculado - $montoEnviado) > 0.02) {
            return response()->json(['error' => "El monto no coincide con el calculado (S/ {$montoCalculado})."], 422);
        }

        DB::transaction(function () use ($request, $reserva, $cantidad, $habitacionesValidas, $montoCalculado) {
            $extension = Extension::create([
                'reserva_id' => $reserva->id,
                'cantidad'   => $cantidad,
            ]);

            foreach ($habitacionesValidas as $hab) {
                $extension->habitaciones()->attach($hab['numero'], [
                    'monto'      => $hab['monto'],
                    'reserva_id' => $reserva->id,
                ]);

                $reserva->habitaciones()->updateExistingPivot($hab['numero'], [
                    'fecha_salida_efectiva' => $hab['nueva_salida'],
                ]);
            }

            $tipoPagoId = TipoPago::where('nombre', 'extension')->value('id');
            Pago::create([
                'reserva_id'       => $reserva->id,
                'monto'            => $montoCalculado,
                'metodo_id'        => $request->metodo_id,
                'tipo_id'          => $tipoPagoId,
                'fecha_pago'       => now(),
                'numero_operacion' => $request->numero_operacion,
            ]);

            // reserva.fecha_salida ya no controla disponibilidad (eso vive en el pivot);
            // se mantiene solo como "salida más tardía" para listados/orden
            $maxSalida = DB::table('reserva_habitaciones')
                ->where('reserva_id', $reserva->id)
                ->max('fecha_salida_efectiva');

            $reserva->fecha_salida = $maxSalida;
            $reserva->save();

            $this->recalcularMontos($reserva);
        });

        return response()->json(['ok' => true]);
    }

    // ── ACCIÓN: Finalizar / Check-out ──
    public function finalizar(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'activa') {
            return response()->json(['error' => 'Solo se pueden finalizar reservas activas.'], 422);
        }

        $request->validate([
            'habitaciones'                  => 'required|array|min:1',
            'habitaciones.*.numero'         => 'required|integer|exists:habitaciones,numero',
            'habitaciones.*.estado_destino' => 'required|in:limpieza,mantenimiento',
            'tipo_comprobante'              => 'required|in:boleta,factura',
            'ruc'                           => 'required_if:tipo_comprobante,factura|nullable|string|size:11',
            'razon_social'                  => 'required_if:tipo_comprobante,factura|nullable|string|max:150',
        ]);

        if ($request->tipo_comprobante === 'factura' && !$this->esRucValido($request->ruc)) {
            return response()->json(['error' => 'El RUC ingresado no es válido.'], 422);
        }

        $reserva->load(['habitaciones']);

        $numerosReserva = $reserva->habitaciones->pluck('numero')->toArray();
        foreach ($request->habitaciones as $hab) {
            if (!in_array($hab['numero'], $numerosReserva)) {
                return response()->json([
                    'error' => "La habitación N°{$hab['numero']} no pertenece a esta reserva."
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $reserva) {
            foreach ($request->habitaciones as $hab) {
                $idEstado = EstadoHabitacion::where('nombre', $hab['estado_destino'])->value('id');
                Habitacion::where('numero', $hab['numero'])->update(['estado_id' => $idEstado]);
            }

            $comprobante = $this->generarComprobante(
                $request->tipo_comprobante,
                $request->tipo_comprobante === 'factura' ? $request->ruc : null,
                $request->tipo_comprobante === 'factura' ? $request->razon_social : null,
            );

            $idFinalizada = EstadoReserva::where('nombre', 'finalizada')->value('id');
            $reserva->update([
                'estado_id'      => $idFinalizada,
                'comprobante_id' => $comprobante->id,
            ]);
        });

        return response()->json([
            'ok' => true,
            'comprobante_url' => route('reservas.comprobante', $reserva->id),
        ]);
    }

    // ── ACCIÓN: Cancelar ──
    public function cancelarInfo(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden cancelar reservas pendientes.'], 422);
        }

        $reserva->load(['devoluciones']);

        $montoPagadoBruto   = round($reserva->pagos()->sum('monto'), 2);
        $ajusteDevoluciones = $reserva->devoluciones->sum(fn($d) => $d->monto_devuelto + $d->monto_retenido);
        $montoPagadoNeto    = round($montoPagadoBruto - $ajusteDevoluciones, 2);

        return response()->json([
            'monto_total'  => (float) $reserva->costo_total,
            'monto_pagado' => $montoPagadoNeto,
        ]);
    }

    public function cancelar(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden cancelar reservas pendientes.'], 422);
        }

        $validador = Validator::make($request->all(), [
            'monto_devuelto'   => 'required|numeric|min:0',
            'metodo_id'        => 'nullable|exists:metodos_pago,id',
            'numero_operacion' => 'nullable|string|max:30',
        ]);

        if ($validador->fails()) {
            return response()->json(['error' => $validador->errors()->first()], 422);
        }

        $reserva->load(['devoluciones']);

        // Recalculado en servidor — nunca confiar en el máximo que manda el cliente
        $montoPagadoBruto   = round($reserva->pagos()->sum('monto'), 2);
        $ajusteDevoluciones = $reserva->devoluciones->sum(fn($d) => $d->monto_devuelto + $d->monto_retenido);
        $montoPagado        = round($montoPagadoBruto - $ajusteDevoluciones, 2);

        $montoDevuelto = round((float) $request->monto_devuelto, 2);

        if ($montoDevuelto > $montoPagado) {
            return response()->json([
                'error' => "El monto a devolver no puede superar lo pagado (S/ " . number_format($montoPagado, 2) . ")."
            ], 422);
        }

        if ($montoDevuelto > 0 && ! $request->filled('metodo_id')) {
            return response()->json(['error' => 'Seleccione un método de devolución.'], 422);
        }

        $metodoNombre    = 'efectivo';
        $numeroOperacion = null;

        if ($montoDevuelto > 0) {
            $metodoObj    = MetodoPago::find($request->metodo_id);
            $metodoNombre = $metodoObj->nombre;

            if ($metodoNombre !== 'efectivo' && !$request->filled('numero_operacion')) {
                return response()->json([
                    'error' => 'El número de operación es obligatorio para este método de devolución.'
                ], 422);
            }
            if ($metodoNombre !== 'efectivo') {
                $numeroOperacion = $request->numero_operacion;
            }
        }

        $montoRetenido = round($montoPagado - $montoDevuelto, 2);

        DB::transaction(function () use ($reserva, $montoDevuelto, $montoRetenido, $metodoNombre, $numeroOperacion) {
            $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');
            foreach ($reserva->habitaciones as $hab) {
                $hab->update(['estado_id' => $idDisponible]);
            }

            $idCancelada = EstadoReserva::where('nombre', 'cancelada')->value('id');
            $reserva->update(['estado_id' => $idCancelada]);

            Devolucion::create([
                'reserva_id'       => $reserva->id,
                'origen'           => 'cancelacion',
                'monto_devuelto'   => $montoDevuelto,
                'monto_retenido'   => $montoRetenido,
                'metodo'           => $metodoNombre,
                'numero_operacion' => $numeroOperacion,
                'fecha_devolucion' => now(),
            ]);

            $this->recalcularMontos($reserva);
        });

        return response()->json(['ok' => true]);
    }

    // Marca 'reservada' las habitaciones con entrada en los próximos 30 min (reemplaza el cron sin configurar)
    public function marcarHabitacionesReservadas(): JsonResponse
    {
        $idPendiente  = EstadoReserva::where('nombre', 'pendiente')->value('id');
        $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');
        $idReservada  = EstadoHabitacion::where('nombre', 'reservada')->value('id');

        $ahora  = now();
        $limite = $ahora->copy()->addMinutes(30);

        $reservas = Reserva::where('estado_id', $idPendiente)
            ->whereBetween('fecha_entrada', [$ahora, $limite])
            ->with('habitaciones')
            ->get();

        $conteo = 0;
        foreach ($reservas as $reserva) {
            foreach ($reserva->habitaciones as $hab) {
                if ($hab->estado_id === $idDisponible) {
                    $hab->update(['estado_id' => $idReservada]);
                    $conteo++;
                }
            }
        }

        return response()->json(['ok' => true, 'marcadas' => $conteo]);
    }
}
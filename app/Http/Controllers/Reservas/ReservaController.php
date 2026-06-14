<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\TipoEstadia;
use App\Models\EstadoReserva;
use App\Models\Habitacion;
use App\Models\EstadoHabitacion;
use App\Models\Huesped;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Models\MetodoPago;
use App\Models\Extension;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function index()
    {
        $tiposEstadia   = TipoEstadia::all();
        $estadosReserva = EstadoReserva::all();
        $tiposDocumento = \App\Models\TipoDocumento::orderBy('nombre')->get();
        $metodosPago    = MetodoPago::all();

        return view('reservas.index', compact(
            'tiposEstadia', 'estadosReserva', 'tiposDocumento', 'metodosPago'
        ));
    }

    public function habitacionesDisponibles(Request $request)
    {
        $request->validate([
            'fecha_entrada'   => 'required|date',
            'fecha_salida'    => 'required|date|after:fecha_entrada',
            'tipo_estadia_id' => 'required|exists:tipos_estadia,id',
        ]);

        $entrada = $request->fecha_entrada;
        $salida  = $request->fecha_salida;
        $salidaConBuffer = date('Y-m-d H:i:s', strtotime($salida . ' +30 minutes'));

        // Habitaciones ocupadas en ese rango (reservas activas o pendientes)
        $idActiva   = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');

        $ocupadas = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])

            ->where(function ($q) use ($entrada, $salidaConBuffer) {
                $q->where('reservas.fecha_entrada', '<', $salidaConBuffer)
                ->where('reservas.fecha_salida',  '>', $entrada);
            }) 
            ->pluck('reserva_habitaciones.habitacion_numero')
            ->toArray();

        // Estado disponible
        $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');

        $habitaciones = Habitacion::with('tipo')
            ->where('activo', 1)
            ->where('estado_id', $idDisponible)
            ->whereNotIn('numero', $ocupadas)
            ->orderBy('numero')
            ->get();

        // Agrupar por piso derivado del número
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

        // Nombre del tipo de estadía para el JS
        $tipoNombre = TipoEstadia::find($request->tipo_estadia_id)->nombre;

        return response()->json([
            'pisos'       => $pisos,
            'tipo_nombre' => $tipoNombre, // 'horas' o 'noches'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha_entrada'    => 'required|date',
            'fecha_salida'     => 'required|date|after:fecha_entrada',
            'tipo_estadia_id'  => 'required|exists:tipos_estadia,id',
            'habitaciones'     => 'required|array|min:1',
            'huespedes'        => 'required|array|min:1',
            'monto_pago'       => 'required|numeric|min:0',
            'metodo_id'        => 'required|exists:metodos_pago,id',
        ]);

        $entrada        = new \DateTime($request->fecha_entrada);
        $ahora          = new \DateTime();
        $diffMinutos    = ($entrada->getTimestamp() - $ahora->getTimestamp()) / 60;
        $esInmediata    = $diffMinutos <= 10;

        $tipoNombre     = TipoEstadia::find($request->tipo_estadia_id)->nombre;
        $franjaJS       = $request->franja; // 'normal','early','madrugada','intermedio','horas'

        // ── Calcular monto total base ──
        $montoBase  = 0;
        $montoEarly = 0;
        $maxTotal = 0;

        foreach ($request->habitaciones as $hab) {
            $numero  = $hab['numero'];
            $habitacion = Habitacion::with('tipo')->where('numero', $numero)->firstOrFail();
            $maxTotal  += $habitacion->tipo->max_huespedes;

            if ($tipoNombre === 'horas') {
                $horas      = $hab['unidades'];
                $montoBase += $habitacion->tipo->precio_hora * $horas;
            } else {
                $noches     = $hab['unidades'];
                $montoBase += $habitacion->tipo->precio_noche * $noches;
                if ($franjaJS === 'early') {
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
            $request, $esInmediata, $tipoNombre, $franjaJS,
            $montoBase, $montoEarly, $montoTotal
        ) {
            // ── Estado ──
            $estadoNombre = $esInmediata ? 'activa' : 'pendiente';
            $estadoId     = EstadoReserva::where('nombre', $estadoNombre)->value('id');

            // ── Crear reserva ──
            $reserva = Reserva::create([
                'usuario_id'      => auth()->id(),
                'tipo_estadia_id' => $request->tipo_estadia_id,
                'fecha_entrada'   => $request->fecha_entrada,
                'fecha_salida'    => $request->fecha_salida,
                'estado_id'       => $estadoId,
                'observacion'     => $request->observacion,
            ]);

            // ── Habitaciones ──
            foreach ($request->habitaciones as $hab) {
                $habitacion = Habitacion::with('tipo')->where('numero', $hab['numero'])->first();
                $precio     = $tipoNombre === 'horas'
                    ? $habitacion->tipo->precio_hora * $hab['unidades']
                    : $habitacion->tipo->precio_noche * $hab['unidades'];

                $reserva->habitaciones()->attach($hab['numero'], [
                    'precio_aplicado' => $precio,
                    'horas'           => $tipoNombre === 'horas' ? $hab['unidades'] : null,
                ]);

                // Si es activa, marcar habitación como ocupada
                if ($esInmediata) {
                    $idOcupada = EstadoHabitacion::where('nombre', 'ocupada')->value('id');
                    $habitacion->update(['estado_id' => $idOcupada]);
                }
            }

            // ── Huéspedes ──
            $reserva->huespedes()->attach($request->huespedes);

            // ── IDs de tipos de pago ──
            $idAdelanto        = TipoPago::where('nombre', 'adelanto')->value('id');
            $idPagoFinal       = TipoPago::where('nombre', 'pago final')->value('id');
            $idIngresoTemprano = TipoPago::where('nombre', 'ingreso temprano')->value('id');

            // ── Pago early (si aplica) ──
            if ($franjaJS === 'early' && $montoEarly > 0) {
                Pago::create([
                    'reserva_id'   => $reserva->id,
                    'usuario_id'   => auth()->id(),
                    'extension_id' => null,
                    'monto'        => $montoEarly,
                    'metodo_id'    => $request->metodo_id,
                    'tipo_id'      => $idIngresoTemprano,
                ]);
            }

            // ── Pago principal ──
            $montoBase     = $request->monto_pago - ($franjaJS === 'early' ? $montoEarly : 0);
            $esCompleto    = abs($request->monto_pago - $montoTotal) < 0.01;
            $tipoPagoId    = $esCompleto ? $idPagoFinal : $idAdelanto;

            Pago::create([
                'reserva_id'   => $reserva->id,
                'usuario_id'   => auth()->id(),
                'extension_id' => null,
                'monto'        => $montoBase,
                'metodo_id'    => $request->metodo_id,
                'tipo_id'      => $tipoPagoId,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    public function filtrar(Request $request)
    {
        $query = Reserva::with([
            'usuario', 'huespedes', 'habitaciones', 'estado', 'tipoEstadia', 'pagos.tipo'
        ])->orderByDesc('id');

        // ── Filtros existentes ──
        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->estado_id);
        }
        if ($request->filled('fecha_entrada')) {
            $query->whereDate('fecha_entrada', $request->fecha_entrada);
        }

        // ── Filtros nuevos ──
        if ($request->filled('habitacion')) {
            $query->whereHas('habitaciones', fn($q) =>
                $q->where('habitacion_numero', $request->habitacion)
            );
        }
        if ($request->filled('huesped')) {
            $query->whereHas('huespedes', fn($q) =>
                $q->where('nombre', 'like', '%' . $request->huesped . '%')
                ->orWhere('num_doc', $request->huesped)
            );
        }

        // ── Carga inicial: solo lo relevante de hoy ──
        if ($request->boolean('inicial')) {
            $hoy = now()->toDateString();
            $idActiva    = \App\Models\EstadoReserva::where('nombre', 'activa')->value('id');
            $idPendiente = \App\Models\EstadoReserva::where('nombre', 'pendiente')->value('id');

            $query->where(function ($q) use ($hoy, $idActiva, $idPendiente) {
                $q->where('estado_id', $idActiva)  // activas ahora
                ->orWhere(function ($q2) use ($hoy, $idPendiente) {
                    $q2->where('estado_id', $idPendiente)
                        ->whereDate('fecha_entrada', $hoy); // pendientes con entrada hoy
                })
                ->orWhereDate('created_at', $hoy); // cualquier reserva creada hoy
            });
        }

        // ── Paginación ──
        $porPagina = 6;
        $pagina    = (int) $request->get('pagina', 1);

        $total = $query->count();
        $items = $query->skip(($pagina - 1) * $porPagina)->take($porPagina)->get();

        return response()->json([
            'data' => $items->map(function ($r) {
                $estado      = $r->estado->nombre;
                $montoHab    = $r->habitaciones->sum('pivot.precio_aplicado');
                $montoEarly  = $r->pagos->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')->sum('monto');
                $montoExt    = $r->pagos->filter(fn($p) => $p->tipo->nombre === 'extension')->sum('monto');
                $montoTotal  = $montoHab + $montoEarly + $montoExt;
                $montoPagado = $r->pagos->sum('monto');
                $saldo       = round($montoTotal - $montoPagado, 2);

                return [
                    'id'               => $r->id,
                    'tipo_estadia'     => ucfirst($r->tipoEstadia->nombre),
                    'fecha_entrada'    => $r->fecha_entrada->format('d/m/Y H:i'),
                    'fecha_salida'     => $r->fecha_salida->format('d/m/Y H:i'),
                    'estado_nombre'    => $estado,
                    'usuario'          => $r->usuario->name,
                    'huesped_principal'=> $r->huespedes->first()?->nombre ?? '—',
                    'huespedes_extra'  => max(0, $r->huespedes->count() - 1),
                    'habitaciones'     => $r->habitaciones->pluck('numero')->join(', '),
                    'monto_total'      => $montoTotal,
                    'monto_pagado'     => $montoPagado,
                    'saldo_pendiente'  => $saldo,
                    'puede_checkin'    => $estado === 'pendiente' && $saldo <= 0,
                    'puede_editar_fechas' => $estado === 'pendiente',
                    'puede_reasignar'  => $estado === 'pendiente',
                    'puede_pago'       => $estado === 'pendiente' && $saldo > 0,
                    'puede_huespedes'  => in_array($estado, ['pendiente', 'activa']),
                    'puede_extension'  => $estado === 'activa',
                    'puede_finalizar'  => $estado === 'activa',
                    'puede_cancelar'   => $estado === 'pendiente',
                ];
            }),
            'total'         => $total,
            'por_pagina'    => $porPagina,
            'pagina_actual' => $pagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    }

    public function show(Reserva $reserva)
    {
        $reserva->load([
            'estado',
            'tipoEstadia',
            'usuario',
            'huespedes.tipoDocumento',
            'habitaciones.tipo',
            'pagos.metodo',
            'pagos.tipo',
            'extensiones.habitaciones',
            'extensiones.pagos.metodo',
        ]);

        $montoHabitaciones  = $reserva->habitaciones->sum('pivot.precio_aplicado');
        $montoEarly         = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')
            ->sum('monto');
        $montoExtensiones   = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'extension')
            ->sum('monto');
        $montoTotal = $montoHabitaciones + $montoEarly + $montoExtensiones;
        $montoPagado = $reserva->pagos->sum('monto');
        $saldo       = round($montoTotal - $montoPagado, 2);

        return response()->json([
            'id'            => $reserva->id,
            'tipo_estadia'  => ucfirst($reserva->tipoEstadia->nombre),
            'fecha_entrada' => $reserva->fecha_entrada->format('d/m/Y H:i'),
            'fecha_salida'  => $reserva->fecha_salida->format('d/m/Y H:i'),
            'estado'        => $reserva->estado->nombre,
            'observacion'   => $reserva->observacion ?? '—',
            'registrado_por'=> $reserva->usuario->name,
            'created_at'    => $reserva->created_at->format('d/m/Y H:i'),

            'habitaciones' => $reserva->habitaciones->map(fn($h) => [
                'numero'          => $h->numero,
                'tipo'            => $h->tipo->nombre,
                'precio_aplicado' => number_format($h->pivot->precio_aplicado, 2),
                'horas'           => $h->pivot->horas,
            ]),

            'huespedes' => $reserva->huespedes->map(fn($h) => [
                'nombre'   => $h->nombre,
                'tipo_doc' => strtoupper($h->tipoDocumento->nombre),
                'num_doc'  => $h->num_doc,
                'telefono' => $h->telefono ?? '—',
            ]),

            'pagos' => $reserva->pagos->map(fn($p) => [
                'monto'   => number_format($p->monto, 2),
                'tipo'    => $p->tipo->nombre,
                'metodo'  => ucfirst($p->metodo->nombre),
                'fecha'   => $p->created_at->format('d/m/Y H:i'),
            ]),

            'extensiones' => $reserva->extensiones->map(fn($e) => [
                'id'           => $e->id,
                'cantidad'     => $e->cantidad,
                'fecha'        => $e->created_at->format('d/m/Y H:i'),
                'habitaciones' => $e->habitaciones->map(fn($h) => [
                    'numero' => $h->numero,
                    'monto'  => number_format($h->pivot->monto, 2),
                ]),
                'pago' => $e->pagos->first() ? [
                    'monto'  => number_format($e->pagos->first()->monto, 2),
                    'metodo' => ucfirst($e->pagos->first()->metodo->nombre),
                ] : null,
            ]),

            'monto_total'  => number_format($montoTotal, 2),
            'monto_pagado' => number_format($montoPagado, 2),
            'saldo'        => number_format($saldo, 2),
        ]);
    }

    public function registrarPago(Request $request, Reserva $reserva)
    {
        $request->validate([
            'monto'     => 'required|numeric|min:0.01',
            'metodo_id' => 'required|exists:metodos_pago,id',
        ]);

        // ── Validar estado ──
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json([
                'error' => 'Solo se puede registrar pago en reservas pendientes.'
            ], 422);
        }

        // ── Calcular saldo actual ──
        $reserva->load(['habitaciones', 'pagos.tipo']);

        $montoHabitaciones = $reserva->habitaciones->sum('pivot.precio_aplicado');
        $montoEarly        = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')
            ->sum('monto');
        $montoExtensiones  = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'extension')
            ->sum('monto');
        $montoTotal  = $montoHabitaciones + $montoEarly + $montoExtensiones;
        $montoPagado = $reserva->pagos->sum('monto');
        $saldo       = round($montoTotal - $montoPagado, 2);

        // ── Validar que aún haya saldo ──
        if ($saldo <= 0) {
            return response()->json([
                'error' => 'Esta reserva ya está pagada al 100%.'
            ], 422);
        }

        // ── Validar monto enviado ──
        $monto = round((float) $request->monto, 2);

        if ($monto > $saldo) {
            return response()->json([
                'error' => 'El monto ingresado (S/ ' . number_format($monto, 2) . ') supera el saldo pendiente (S/ ' . number_format($saldo, 2) . ').'
            ], 422);
        }

        // ── Determinar tipo de pago ──
        $esFinal    = abs($monto - $saldo) < 0.01;
        $tipoPagoId = TipoPago::where('nombre', $esFinal ? 'pago final' : 'adelanto')->value('id');

        // ── Crear pago ──
        Pago::create([
            'reserva_id'   => $reserva->id,
            'usuario_id'   => auth()->id(),
            'extension_id' => null,
            'monto'        => $monto,
            'metodo_id'    => $request->metodo_id,
            'tipo_id'      => $tipoPagoId,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─── PRECHECK: info para el modal de check-in ───
    public function checkinInfo(Reserva $reserva)
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'La reserva no está en estado pendiente.'], 422);
        }

        $reserva->load(['habitaciones.tipo', 'pagos.tipo', 'tipoEstadia']);

        $montoHabitaciones = $reserva->habitaciones->sum('pivot.precio_aplicado');
        $montoEarly        = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')
            ->sum('monto');
        $montoExtensiones  = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'extension')
            ->sum('monto');
        $saldo = round(($montoHabitaciones + $montoEarly + $montoExtensiones) - $reserva->pagos->sum('monto'), 2);

        if ($saldo > 0) {
            return response()->json(['error' => 'La reserva tiene saldo pendiente de S/ ' . number_format($saldo, 2) . '. Registre el pago antes de hacer check-in.'], 422);
        }

        $ahora       = now();
        $horaActual  = $ahora->hour * 60 + $ahora->minute;
        $EARLY_FIN   = 11 * 60;
        $esPorHoras  = $reserva->tipoEstadia->nombre === 'horas';

        // ── Calcular nueva fecha_salida según tipo ──
        $nuevaSalida = null;

        if ($esPorHoras) {
            $horas      = $reserva->habitaciones->first()->pivot->horas ?? 2;
            $salida     = $ahora->copy()->addHours($horas);
            $mins       = $salida->minute;
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
            // Noches: calcular cuántas noches pagó
            $entradaOriginal = $reserva->fecha_entrada;
            $salidaOriginal  = $reserva->fecha_salida;
            $nochesPagadas   = $entradaOriginal->diffInDays($salidaOriginal);

            // Nueva salida = ahora + noches pagadas, a las 11AM
            $nuevaSalida = $ahora->copy()->addDays($nochesPagadas)->setHour(11)->setMinute(0)->setSecond(0);

            // Si llega en madrugada (antes de las 6AM), cuenta como noche del mismo día
            if ($horaActual <= 6 * 60) {
                $nuevaSalida = $ahora->copy()->setHour(11)->setMinute(0)->setSecond(0);
                if ($nochesPagadas > 1) {
                    $nuevaSalida->addDays($nochesPagadas - 1);
                }
            }
        }

        // ── Verificar disponibilidad en el nuevo rango completo ──
        $idActiva        = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente     = EstadoReserva::where('nombre', 'pendiente')->value('id');
        $habitacionesInfo = [];
        $todasLibres     = true;

        foreach ($reserva->habitaciones as $hab) {
            // Verificar conflictos en todo el nuevo rango
            $conflicto = DB::table('reserva_habitaciones')
                ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
                ->join('tipos_estadia', 'tipos_estadia.id', '=', 'reservas.tipo_estadia_id')
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])
                ->where('reservas.id', '!=', $reserva->id)
                ->where('reservas.fecha_entrada', '<', $nuevaSalida)
                ->where('reservas.fecha_salida',  '>', $ahora)
                ->select('reservas.fecha_salida', 'tipos_estadia.nombre as tipo_estadia')
                ->orderBy('reservas.fecha_entrada')
                ->first();

            if ($conflicto) {
                $todasLibres    = false;
                $fechaSalida    = \Carbon\Carbon::parse($conflicto->fecha_salida);
                $disponibleA    = $conflicto->tipo_estadia === 'noches'
                    ? '11:00 AM'
                    : $fechaSalida->format('H:i');

                $habitacionesInfo[] = [
                    'numero'       => $hab->numero,
                    'disponible'   => false,
                    'disponible_a' => $disponibleA,
                ];
            } else {
                $habitacionesInfo[] = [
                    'numero'     => $hab->numero,
                    'disponible' => true,
                ];
            }
        }

        // ── Recargo early ──
        $hayRecargo      = false;
        $recargo         = 0;
        $esAnticipada    = $ahora->lt($reserva->fecha_entrada);

        if ($todasLibres && !$esPorHoras) {
            $entradaOriginalHora = $reserva->fecha_entrada->hour * 60 + $reserva->fecha_entrada->minute;
            if ($horaActual < $EARLY_FIN && $entradaOriginalHora >= $EARLY_FIN) {
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

    // ─── CONFIRMAR CHECK-IN ───
    public function checkin(Request $request, Reserva $reserva)
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'La reserva no está en estado pendiente.'], 422);
        }

        $reserva->load(['habitaciones.tipo', 'pagos.tipo', 'tipoEstadia']);

        $montoHabitaciones = $reserva->habitaciones->sum('pivot.precio_aplicado');
        $montoEarly        = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')
            ->sum('monto');
        $montoExtensiones  = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'extension')
            ->sum('monto');
        $saldo = round(($montoHabitaciones + $montoEarly + $montoExtensiones) - $reserva->pagos->sum('monto'), 2);

        if ($saldo > 0) {
            return response()->json(['error' => 'La reserva tiene saldo pendiente.'], 422);
        }

        $ahora      = now();
        $horaActual = $ahora->hour * 60 + $ahora->minute;
        $EARLY_FIN  = 11 * 60;
        $esPorHoras = $reserva->tipoEstadia->nombre === 'horas';

        // ── Calcular nueva fecha_salida ──
        $nuevaSalida = null;

        if ($esPorHoras) {
            $horas   = $reserva->habitaciones->first()->pivot->horas ?? 2;
            $salida  = $ahora->copy()->addHours($horas);
            $mins    = $salida->minute;
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
            $nochesPagadas = $reserva->fecha_entrada->diffInDays($reserva->fecha_salida);
            $nuevaSalida   = $ahora->copy()->addDays($nochesPagadas)->setHour(11)->setMinute(0)->setSecond(0);

            if ($horaActual <= 6 * 60) {
                $nuevaSalida = $ahora->copy()->setHour(11)->setMinute(0)->setSecond(0);
                if ($nochesPagadas > 1) {
                    $nuevaSalida->addDays($nochesPagadas - 1);
                }
            }
        }

        // ── Recargo early (solo noches) ──
        $hayRecargo = false;
        $recargo    = 0;

        if (!$esPorHoras) {
            $entradaOriginalHora = $reserva->fecha_entrada->hour * 60 + $reserva->fecha_entrada->minute;
            if ($horaActual < $EARLY_FIN && $entradaOriginalHora >= $EARLY_FIN) {
                $hayRecargo = true;
                foreach ($reserva->habitaciones as $hab) {
                    $recargo += $hab->tipo->precio_hora * 2;
                }
            }
        }

        if ($hayRecargo) {
            $request->validate([
                'metodo_id' => 'required|exists:metodos_pago,id',
            ]);
        }

        DB::transaction(function () use ($request, $reserva, $ahora, $nuevaSalida, $hayRecargo, $recargo) {
            // Pago recargo early si aplica
            if ($hayRecargo && $recargo > 0) {
                $idIngresoTemprano = TipoPago::where('nombre', 'ingreso temprano')->value('id');
                Pago::create([
                    'reserva_id'   => $reserva->id,
                    'usuario_id'   => auth()->id(),
                    'extension_id' => null,
                    'monto'        => $recargo,
                    'metodo_id'    => $request->metodo_id,
                    'tipo_id'      => $idIngresoTemprano,
                ]);
            }

            // Actualizar fechas
            $reserva->fecha_entrada = $ahora;
            $reserva->fecha_salida  = $nuevaSalida;

            // Estado activa
            $idActiva       = EstadoReserva::where('nombre', 'activa')->value('id');
            $reserva->estado_id = $idActiva;
            $reserva->save();

            // Habitaciones ocupadas
            $idOcupada = EstadoHabitacion::where('nombre', 'ocupada')->value('id');
            foreach ($reserva->habitaciones as $hab) {
                $hab->update(['estado_id' => $idOcupada]);
            }
        });

        return response()->json(['ok' => true]);
    }


    public function extensionInfo(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'activa') {
            return response()->json(['error' => 'Solo se pueden extender reservas activas.'], 422);
        }
    
        $reserva->load(['habitaciones.tipo', 'tipoEstadia']);
    
        $esPorHoras  = $reserva->tipoEstadia->nombre === 'horas';
        $cantidad    = (int) request('cantidad', 1);
    
        if ($cantidad < 1) {
            return response()->json(['error' => 'La cantidad mínima es 1.'], 422);
        }
    
        // ── Nueva fecha_salida según tipo ──
        $salidaActual = $reserva->fecha_salida->copy();
    
        if ($esPorHoras) {
            $nuevaSalida = $salidaActual->copy()->addHours($cantidad);
        } else {
            // Noches: la salida siempre es a las 11:00 AM
            $nuevaSalida = $salidaActual->copy()->addDays($cantidad)->setHour(11)->setMinute(0)->setSecond(0);
        }
    
        // ── Verificar conflictos por habitación ──
        $idActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');
    
        $habitaciones = [];
    
        foreach ($reserva->habitaciones as $hab) {
            $conflicto = DB::table('reserva_habitaciones')
                ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
                ->join('estados_reserva', 'estados_reserva.id', '=', 'reservas.estado_id')
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])
                ->where('reservas.id', '!=', $reserva->id)
                // Conflicto: la otra reserva empieza ANTES de que termina la extensión
                // y termina DESPUÉS de que empieza la extensión (= el tramo nuevo)
                ->where('reservas.fecha_entrada', '<', $nuevaSalida)
                ->where('reservas.fecha_salida',  '>', $salidaActual)
                ->select(
                    'reservas.id as reserva_id',
                    'reservas.fecha_entrada',
                    'reservas.fecha_salida',
                    'estados_reserva.nombre as estado_reserva'
                )
                ->orderBy('reservas.fecha_entrada')
                ->first();
    
            if ($esPorHoras) {
                $montoExtension = round($hab->tipo->precio_hora * $cantidad, 2);
                $unidadLabel    = $cantidad === 1 ? '1 hora' : "{$cantidad} horas";
            } else {
                $montoExtension = round($hab->tipo->precio_noche * $cantidad, 2);
                $unidadLabel    = $cantidad === 1 ? '1 noche' : "{$cantidad} noches";
            }
    
            if ($conflicto) {
                $habitaciones[] = [
                    'numero'      => $hab->numero,
                    'tipo'        => $hab->tipo->nombre,
                    'disponible'  => false,
                    'reserva_id'  => $conflicto->reserva_id,
                    'estado_conflicto' => $conflicto->estado_reserva,
                    'monto'       => $montoExtension,
                ];
            } else {
                $habitaciones[] = [
                    'numero'     => $hab->numero,
                    'tipo'       => $hab->tipo->nombre,
                    'disponible' => true,
                    'monto'      => $montoExtension,
                ];
            }
        }
    
        $disponibles = array_filter($habitaciones, fn($h) => $h['disponible']);
        $montoTotal  = round(array_sum(array_column(array_values($disponibles), 'monto')), 2);
    
        return response()->json([
            'tipo_estadia'   => $reserva->tipoEstadia->nombre,
            'es_por_horas'   => $esPorHoras,
            'salida_actual'  => $salidaActual->format('d/m/Y H:i'),
            'nueva_salida'   => $nuevaSalida->format('d/m/Y H:i'),
            'unidad_label'   => $unidadLabel ?? '',
            'habitaciones'   => array_values($habitaciones),
            'monto_total'    => $montoTotal,
            'hay_disponibles'=> count($disponibles) > 0,
        ]);
    }
 
    // ─── POST /reservas/{reserva}/extension ──────────────────────
    public function agregarExtension(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'activa') {
            return response()->json(['error' => 'Solo se pueden extender reservas activas.'], 422);
        }
    
        $request->validate([
            'cantidad'   => 'required|integer|min:1',
            'metodo_id'  => 'required|exists:metodos_pago,id',
            'habitaciones' => 'required|array|min:1',
            'habitaciones.*' => 'integer',
        ]);
    
        $reserva->load(['habitaciones.tipo', 'tipoEstadia']);
    
        $esPorHoras  = $reserva->tipoEstadia->nombre === 'horas';
        $cantidad    = (int) $request->cantidad;
        $salidaActual = $reserva->fecha_salida->copy();
    
        if ($esPorHoras) {
            $nuevaSalida = $salidaActual->copy()->addHours($cantidad);
        } else {
            $nuevaSalida = $salidaActual->copy()->addDays($cantidad)->setHour(11)->setMinute(0)->setSecond(0);
        }
    
        // ── Verificar conflictos en el momento de confirmar ──
        $idActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');
    
        $numerosEnviados = $request->habitaciones;
        $habitacionesValidas = [];
    
        foreach ($reserva->habitaciones as $hab) {
            if (!in_array($hab->numero, $numerosEnviados)) continue;
    
            $conflicto = DB::table('reserva_habitaciones')
                ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
                ->where('reserva_habitaciones.habitacion_numero', $hab->numero)
                ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])
                ->where('reservas.id', '!=', $reserva->id)
                ->where('reservas.fecha_entrada', '<', $nuevaSalida)
                ->where('reservas.fecha_salida',  '>', $salidaActual)
                ->exists();
    
            if (!$conflicto) {
                $monto = $esPorHoras
                    ? round($hab->tipo->precio_hora  * $cantidad, 2)
                    : round($hab->tipo->precio_noche * $cantidad, 2);
    
                $habitacionesValidas[] = [
                    'numero' => $hab->numero,
                    'monto'  => $monto,
                ];
            }
        }
    
        if (empty($habitacionesValidas)) {
            return response()->json(['error' => 'Ninguna de las habitaciones seleccionadas está disponible para extender.'], 422);
        }
    
        // ── Validar monto enviado vs calculado ──
        $montoCalculado = round(array_sum(array_column($habitacionesValidas, 'monto')), 2);
        $montoEnviado   = round((float) $request->monto, 2);
    
        if (abs($montoCalculado - $montoEnviado) > 0.02) {
            return response()->json([
                'error' => "El monto no coincide con el calculado (S/ {$montoCalculado})."
            ], 422);
        }
    
        DB::transaction(function () use (
            $request, $reserva, $nuevaSalida, $cantidad, $habitacionesValidas, $montoCalculado
        ) {
            // 1. Crear extensión
            $extension = Extension::create([
                'reserva_id' => $reserva->id,
                'usuario_id' => auth()->id(),
                'cantidad'   => $cantidad,
            ]);
    
            // 2. Crear extension_habitaciones
            foreach ($habitacionesValidas as $hab) {
                $extension->habitaciones()->attach($hab['numero'], ['monto' => $hab['monto']]);
            }
    
            // 3. Crear pago tipo 'extension'
            $tipoPagoId = TipoPago::where('nombre', 'extension')->value('id');
            Pago::create([
                'reserva_id'   => $reserva->id,
                'usuario_id'   => auth()->id(),
                'extension_id' => $extension->id,
                'monto'        => $montoCalculado,
                'metodo_id'    => $request->metodo_id,
                'tipo_id'      => $tipoPagoId,
            ]);
    
            // 4. Actualizar fecha_salida de la reserva
            $reserva->fecha_salida = $nuevaSalida;
            $reserva->save();
        });
    
        return response()->json(['ok' => true]);
    }

    // ─── PATCH /reservas/{reserva}/finalizar ─────────────────────
    public function finalizar(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'activa') {
            return response()->json(['error' => 'Solo se pueden finalizar reservas activas.'], 422);
        }
    
        $request->validate([
            'habitaciones'                  => 'required|array|min:1',
            'habitaciones.*.numero'         => 'required|integer|exists:habitaciones,numero',
            'habitaciones.*.estado_destino' => 'required|in:limpieza,mantenimiento',
        ]);
    
        $reserva->load(['habitaciones']);
    
        // Verificar que los números enviados corresponden a la reserva
        $numerosReserva = $reserva->habitaciones->pluck('numero')->toArray();
        foreach ($request->habitaciones as $hab) {
            if (!in_array($hab['numero'], $numerosReserva)) {
                return response()->json([
                    'error' => "La habitación N°{$hab['numero']} no pertenece a esta reserva."
                ], 422);
            }
        }
    
        DB::transaction(function () use ($request, $reserva) {
            // 1. Actualizar estado de cada habitación
            foreach ($request->habitaciones as $hab) {
                $idEstado = EstadoHabitacion::where('nombre', $hab['estado_destino'])->value('id');
                Habitacion::where('numero', $hab['numero'])->update(['estado_id' => $idEstado]);
            }
    
            // 2. Reserva → finalizada
            $idFinalizada = EstadoReserva::where('nombre', 'finalizada')->value('id');
            $reserva->update(['estado_id' => $idFinalizada]);
        });
    
        return response()->json(['ok' => true]);
    }

    public function huespedInfo(Reserva $reserva): JsonResponse
    {
        // Solo pendiente o activa
        $estadosPermitidos = ['pendiente', 'activa'];
        if (! in_array($reserva->estado->nombre, $estadosPermitidos)) {
            return response()->json(['error' => 'No se puede editar huéspedes en este estado.'], 422);
        }

        $reserva->load(['huespedes.tipoDocumento', 'habitaciones.tipo']);
    
        // Huéspedes actuales
        $huespedes = $reserva->huespedes->map(fn($h) => [
            'id'       => $h->id,
            'nombre'   => $h->nombre,
            'tipo_doc' => strtoupper($h->tipoDocumento->nombre),
            'num_doc'  => $h->num_doc,
            'telefono' => $h->telefono ?? '—',
        ]);
    
        // Máximo permitido = suma de max_huespedes de habitaciones
        $maxPermitido = $reserva->habitaciones->sum(fn($h) => $h->tipo->max_huespedes);
    
        return response()->json([
            'huespedes'     => $huespedes,
            'max_permitido' => $maxPermitido,
            'estado'        => $reserva->estado->nombre,
        ]);
    }
    
    // ─── PATCH /reservas/{reserva}/huespedes ─────────────────────
    public function editarHuespedes(Request $request, Reserva $reserva): JsonResponse
    {
        // Solo pendiente o activa
        $estadosPermitidos = ['pendiente', 'activa'];
        if (! in_array($reserva->estado->nombre, $estadosPermitidos)) {
            return response()->json(['error' => 'No se puede editar huéspedes en este estado.'], 422);
        }

        $reserva->load(['habitaciones.tipo']);

    
        $request->validate([
            'huespedes'   => ['required', 'array', 'min:1'],
            'huespedes.*' => ['integer', 'exists:huespedes,id'],
        ]);
    
        $ids = $request->huespedes;
    
        // Mínimo 1 huésped
        if (count($ids) < 1) {
            return response()->json(['error' => 'La reserva debe tener al menos un huésped.'], 422);
        }
    
        // Máximo = suma de max_huespedes de habitaciones
        $maxPermitido = $reserva->habitaciones->sum(fn($h) => $h->tipo->max_huespedes);
        if (count($ids) > $maxPermitido) {
            return response()->json([
                'error' => "Máximo permitido: {$maxPermitido} huésped(es) para las habitaciones de esta reserva.",
            ], 422);
        }
    
        // Sync — agrega los nuevos, elimina los que no están
        $reserva->huespedes()->sync($ids);
    
        return response()->json(['ok' => true]);
    }

    // ─── GET /reservas/{reserva}/editar-fechas-info ──────────────
    public function editarFechasInfo(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden editar reservas pendientes.'], 422);
        }

        $reserva->load(['habitaciones.tipo', 'tipoEstadia', 'pagos.tipo']);

        // Calcular lo ya pagado
        $montoHabitaciones = $reserva->habitaciones->sum('pivot.precio_aplicado');
        $montoEarly        = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')
            ->sum('monto');
        $montoTotal  = $montoHabitaciones + $montoEarly;
        $montoPagado = $reserva->pagos->sum('monto');

        return response()->json([
            'tipo_estadia_id'  => $reserva->tipo_estadia_id,
            'tipo_estadia'     => $reserva->tipoEstadia->nombre,
            'fecha_entrada'    => $reserva->fecha_entrada->format('Y-m-d\TH:i'),
            'fecha_salida'     => $reserva->fecha_salida->format('Y-m-d\TH:i'),
            'observacion'      => $reserva->observacion ?? '',
            'monto_total'      => round($montoTotal, 2),
            'monto_pagado'     => round($montoPagado, 2),
            'habitaciones'     => $reserva->habitaciones->map(fn($h) => [
                'numero'           => $h->numero,
                'tipo_nombre'      => $h->tipo->nombre,
                'precio_hora_raw'  => (float) $h->tipo->precio_hora,
                'precio_noche_raw' => (float) $h->tipo->precio_noche,
            ]),
        ]);
    }

    // ─── PATCH /reservas/{reserva}/editar-fechas ─────────────────
    public function editarFechas(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden editar reservas pendientes.'], 422);
        }

        $request->validate([
            'tipo_estadia_id' => 'required|exists:tipos_estadia,id',
            'fecha_entrada'   => 'required|date',
            'fecha_salida'    => 'required|date|after:fecha_entrada',
            'observacion'     => 'nullable|string|max:255',
            'franja'          => 'nullable|string',
            // Pago obligatorio solo en Caso A
            'monto_pago'      => 'nullable|numeric|min:0.01',
            'metodo_id'       => 'nullable|exists:metodos_pago,id',
        ]);

        $ahora = new \DateTime();
        $entrada = new \DateTime($request->fecha_entrada);
        if ($entrada < $ahora) {
            return response()->json([
                'error' => 'La fecha de entrada no puede ser anterior a la fecha y hora actual.'
            ], 422);
        }

        $reserva->load(['habitaciones.tipo', 'pagos.tipo']);

        $tipoNombre = TipoEstadia::find($request->tipo_estadia_id)->nombre;
        $franja     = $request->franja ?? 'normal';

        // ── Recalcular nuevo monto total ──
        $nuevoMontoBase  = 0;
        $nuevoMontoEarly = 0;
        $entrada = new \DateTime($request->fecha_entrada);
        $salida  = new \DateTime($request->fecha_salida);

        foreach ($reserva->habitaciones as $hab) {
            if ($tipoNombre === 'horas') {
                $horas           = round(($salida->getTimestamp() - $entrada->getTimestamp()) / 3600);
                $nuevoMontoBase += $hab->tipo->precio_hora * $horas;
            } else {
                $entDia  = new \DateTime($entrada->format('Y-m-d'));
                $salDia  = new \DateTime($salida->format('Y-m-d'));
                $diff    = $entDia->diff($salDia)->days;
                $noches  = $franja === 'madrugada'
                    ? ($diff === 0 ? 1 : $diff + 1)
                    : ($diff < 1 ? 1 : $diff);
                $nuevoMontoBase += $hab->tipo->precio_noche * $noches;
                if ($franja === 'early') {
                    $nuevoMontoEarly += $hab->tipo->precio_hora * 2;
                }
            }
        }

        $nuevoTotal = round($nuevoMontoBase + $nuevoMontoEarly, 2);

        // ── Calcular pagado actual ──
        $montoEarlyActual = $reserva->pagos
            ->filter(fn($p) => $p->tipo->nombre === 'ingreso temprano')
            ->sum('monto');
        $montoPagado = round($reserva->pagos->sum('monto'), 2);

        // ── Determinar caso ──
        $minimo50  = round($nuevoTotal * 0.5, 2);
        $diferencia = round($nuevoTotal - $montoPagado, 2);

        // Caso A: nuevo total mayor y pagado no llega al 50%
        $esCasoA = $nuevoTotal > $montoPagado && $montoPagado < $minimo50;

        if ($esCasoA) {
            // Validar que venga el pago obligatorio
            if (! $request->filled('monto_pago') || ! $request->filled('metodo_id')) {
                return response()->json(['error' => 'Se requiere un pago adicional para cubrir el mínimo del 50%.'], 422);
            }

            $montoPago     = round((float) $request->monto_pago, 2);
            $minimoReq     = round($minimo50 - $montoPagado, 2);   // diferencia hasta llegar al 50%
            $maximoPermitido = $diferencia;                          // hasta cubrir el 100%

            if ($montoPago < $minimoReq) {
                return response()->json([
                    'error' => "El pago mínimo requerido es S/ " . number_format($minimoReq, 2) . "."
                ], 422);
            }
            if ($montoPago > $maximoPermitido) {
                return response()->json([
                    'error' => "El pago no puede superar el saldo pendiente de S/ " . number_format($maximoPermitido, 2) . "."
                ], 422);
            }
        }

        // Caso C: nuevo total menor que lo pagado — ajustar último pago
        $esCasoC = $nuevoTotal < $montoPagado;

        DB::transaction(function () use (
            $request, $reserva, $tipoNombre, $franja,
            $nuevoTotal, $montoPagado, $esCasoA, $esCasoC,
            $nuevoMontoEarly, $minimo50
        ) {
            $entrada = new \DateTime($request->fecha_entrada);
            $salida  = new \DateTime($request->fecha_salida);

            // 1. Actualizar reserva
            $reserva->update([
                'tipo_estadia_id' => $request->tipo_estadia_id,
                'fecha_entrada'   => $request->fecha_entrada,
                'fecha_salida'    => $request->fecha_salida,
                'observacion'     => $request->observacion,
            ]);

            // 2. Actualizar precio_aplicado y horas en reserva_habitaciones
            foreach ($reserva->habitaciones as $hab) {
                if ($tipoNombre === 'horas') {
                    $horas  = round(($salida->getTimestamp() - $entrada->getTimestamp()) / 3600);
                    $precio = $hab->tipo->precio_hora * $horas;
                    $reserva->habitaciones()->updateExistingPivot($hab->numero, [
                        'precio_aplicado' => $precio,
                        'horas'           => $horas,
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
                        'horas'           => null,
                    ]);
                }
            }

            // 3. Caso A — registrar pago adicional
            if ($esCasoA) {
                $montoPago  = round((float) $request->monto_pago, 2);
                $nuevoTotal = round($nuevoTotal ?? 0, 2);
                $esFinal    = abs(($montoPagado + $montoPago) - $nuevoTotal) < 0.01;
                $tipoPagoId = TipoPago::where('nombre', $esFinal ? 'pago final' : 'adelanto')->value('id');

                Pago::create([
                    'reserva_id'   => $reserva->id,
                    'usuario_id'   => auth()->id(),
                    'extension_id' => null,
                    'monto'        => $montoPago,
                    'metodo_id'    => $request->metodo_id,
                    'tipo_id'      => $tipoPagoId,
                ]);
            }

            // 4. Caso C — reducir el último pago al nuevo total
            if ($esCasoC) {
                $ultimoPago = $reserva->pagos()
                    ->whereHas('tipo', fn($q) => $q->whereIn('nombre', ['adelanto', 'pago final']))
                    ->orderByDesc('id')
                    ->first();

                if ($ultimoPago) {
                    // El monto del último pago se reduce para que el total pagado = nuevo total
                    $otrosPagos    = $reserva->pagos->where('id', '!=', $ultimoPago->id)->sum('monto');
                    $nuevoMontoPago = round($nuevoTotal - $otrosPagos, 2);
                    if ($nuevoMontoPago < 0) $nuevoMontoPago = 0;
                    $ultimoPago->update(['monto' => $nuevoMontoPago]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    // ─── GET /reservas/{reserva}/reasignar-info ──────────────────
    public function reasignarInfo(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden reasignar habitaciones en reservas pendientes.'], 422);
        }

        $reserva->load(['habitaciones.tipo', 'tipoEstadia']);

        $idActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');
        $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');

        // Para cada habitación de la reserva, buscar alternativas del mismo tipo
        $habitaciones = $reserva->habitaciones->map(function ($hab) use (
            $reserva, $idActiva, $idPendiente, $idDisponible
        ) {
            // Habitaciones del mismo tipo que estén disponibles en el rango
            // y no sean la misma habitación actual
            $ocupadasEnRango = DB::table('reserva_habitaciones')
                ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
                ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])
                ->where('reservas.id', '!=', $reserva->id)
                ->where('reservas.fecha_entrada', '<', $reserva->fecha_salida)
                ->where('reservas.fecha_salida',  '>', $reserva->fecha_entrada)
                ->pluck('reserva_habitaciones.habitacion_numero')
                ->toArray();

            // Excluir también las habitaciones que ya están en esta misma reserva
            $enEstaReserva = $reserva->habitaciones->pluck('numero')->toArray();

            $alternativas = Habitacion::with('tipo')
                ->where('tipo_id', $hab->tipo_id)
                ->where('activo', 1)
                ->where('estado_id', $idDisponible)
                ->where('numero', '!=', $hab->numero)
                ->whereNotIn('numero', $ocupadasEnRango)
                ->whereNotIn('numero', $enEstaReserva)
                ->orderBy('numero')
                ->get()
                ->map(fn($a) => [
                    'numero'     => $a->numero,
                    'tipo_nombre'=> $a->tipo->nombre,
                ]);

            return [
                'numero'        => $hab->numero,
                'tipo_id'       => $hab->tipo_id,
                'tipo_nombre'   => $hab->tipo->nombre,
                'precio_aplicado' => number_format($hab->pivot->precio_aplicado, 2),
                'horas'         => $hab->pivot->horas,
                'alternativas'  => $alternativas,
            ];
        });

        return response()->json([
            'habitaciones'  => $habitaciones,
            'tipo_estadia'  => $reserva->tipoEstadia->nombre,
            'fecha_entrada' => $reserva->fecha_entrada->format('d/m/Y H:i'),
            'fecha_salida'  => $reserva->fecha_salida->format('d/m/Y H:i'),
        ]);
    }

    // ─── PATCH /reservas/{reserva}/reasignar ─────────────────────
    public function reasignar(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden reasignar habitaciones en reservas pendientes.'], 422);
        }

        $request->validate([
            'cambios'               => 'required|array|min:1',
            'cambios.*.de'          => 'required|integer|exists:habitaciones,numero',
            'cambios.*.a'           => 'required|integer|exists:habitaciones,numero|different:cambios.*.de',
        ]);

        $reserva->load(['habitaciones.tipo']);

        $idActiva    = EstadoReserva::where('nombre', 'activa')->value('id');
        $idPendiente = EstadoReserva::where('nombre', 'pendiente')->value('id');
        $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');

        // Validar cada cambio
        foreach ($request->cambios as $cambio) {
            $de = $cambio['de'];
            $a  = $cambio['a'];

            // Verificar que 'de' pertenece a la reserva
            $habActual = $reserva->habitaciones->firstWhere('numero', $de);
            if (!$habActual) {
                return response()->json([
                    'error' => "La habitación N°{$de} no pertenece a esta reserva."
                ], 422);
            }

            // Verificar que 'a' es del mismo tipo
            $habNueva = Habitacion::with('tipo')->where('numero', $a)->first();
            if (!$habNueva || $habNueva->tipo_id !== $habActual->tipo_id) {
                return response()->json([
                    'error' => "La habitación N°{$a} no es del mismo tipo que la N°{$de}."
                ], 422);
            }

            // Verificar que 'a' está disponible en el rango
            $conflicto = DB::table('reserva_habitaciones')
                ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
                ->where('reserva_habitaciones.habitacion_numero', $a)
                ->whereIn('reservas.estado_id', [$idActiva, $idPendiente])
                ->where('reservas.id', '!=', $reserva->id)
                ->where('reservas.fecha_entrada', '<', $reserva->fecha_salida)
                ->where('reservas.fecha_salida',  '>', $reserva->fecha_entrada)
                ->exists();

            if ($conflicto) {
                return response()->json([
                    'error' => "La habitación N°{$a} no está disponible en el rango de la reserva."
                ], 422);
            }

            // Verificar que 'a' está disponible (estado)
            if ($habNueva->estado_id !== $idDisponible) {
                return response()->json([
                    'error' => "La habitación N°{$a} no está en estado disponible."
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $reserva) {
            foreach ($request->cambios as $cambio) {
                $de = $cambio['de'];
                $a  = $cambio['a'];

                $habActual = $reserva->habitaciones->firstWhere('numero', $de);

                // Copiar pivot actual (precio y horas no cambian)
                $pivotData = [
                    'precio_aplicado' => $habActual->pivot->precio_aplicado,
                    'horas'           => $habActual->pivot->horas,
                ];

                // Quitar la habitación anterior y agregar la nueva
                $reserva->habitaciones()->detach($de);
                $reserva->habitaciones()->attach($a, $pivotData);
            }
        });

        return response()->json(['ok' => true]);
    }    

    // ─── PATCH /reservas/{reserva}/cancelar ──────────────────────
    public function cancelar(Reserva $reserva): JsonResponse
    {
        if ($reserva->estado->nombre !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden cancelar reservas pendientes.'], 422);
        }

        DB::transaction(function () use ($reserva) {
            // Habitaciones → disponible
            $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');
            foreach ($reserva->habitaciones as $hab) {
                $hab->update(['estado_id' => $idDisponible]);
            }

            // Reserva → cancelada
            $idCancelada = EstadoReserva::where('nombre', 'cancelada')->value('id');
            $reserva->update(['estado_id' => $idCancelada]);
        });

        return response()->json(['ok' => true]);
    }
}
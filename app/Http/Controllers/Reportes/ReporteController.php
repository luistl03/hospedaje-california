<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Habitacion;
use App\Models\Pago;
use App\Models\Devolucion;
use App\Models\EstadoReserva;
use App\Models\Huesped;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    // Resuelve el rango de fechas del request, con default de "últimos 30 días"
    private function resolverRango(Request $request): array
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->desde)->startOfDay()
            : now()->subDays(29)->startOfDay();

        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->hasta)->endOfDay()
            : now()->endOfDay();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        return [$desde, $hasta];
    }

    // Calcula todas las métricas del rango dado (usado en index y en datos)
    private function calcularMetricas(Carbon $desde, Carbon $hasta): array
    {
        // KPIs base
        $ingresosBrutos = Pago::whereBetween('fecha_pago', [$desde, $hasta])->sum('monto');

        $devolucionesPeriodo = Devolucion::whereBetween('fecha_devolucion', [$desde, $hasta])
            ->sum('monto_devuelto');

        $ingresosNetos = round($ingresosBrutos - $devolucionesPeriodo, 2);

        $reservasNuevas = Reserva::whereBetween('created_at', [$desde, $hasta])->count();

        $idCancelada = EstadoReserva::where('nombre', 'cancelada')->value('id');
        $reservasCanceladas = Reserva::where('estado_id', $idCancelada)
            ->whereBetween('updated_at', [$desde, $hasta])
            ->count();

        $tasaCancelacion = $reservasNuevas > 0
            ? round(($reservasCanceladas / $reservasNuevas) * 100, 1)
            : 0;

        $ticketPromedio = $reservasNuevas > 0
            ? round($ingresosBrutos / $reservasNuevas, 2)
            : 0;

        // Ocupación: room-nights ocupados / room-nights disponibles en el rango
        $totalHabitaciones = Habitacion::where('activo', 1)->count();
        $diasPeriodo       = $desde->diffInDays($hasta) + 1;
        $capacidadTotal    = max(1, $totalHabitaciones * $diasPeriodo);

        $idsEstadosOcupacion = EstadoReserva::whereIn('nombre', ['activa', 'finalizada'])->pluck('id');

        $intervalos = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->whereIn('reservas.estado_id', $idsEstadosOcupacion)
            ->where('reservas.fecha_entrada', '<=', $hasta)
            ->where('reserva_habitaciones.fecha_salida_efectiva', '>=', $desde)
            ->select('reservas.fecha_entrada as entrada', 'reserva_habitaciones.fecha_salida_efectiva as salida')
            ->get();

        $roomNights = $intervalos->sum(function ($r) use ($desde, $hasta) {
            $ini = Carbon::parse($r->entrada)->max($desde);
            $fin = Carbon::parse($r->salida)->min($hasta);
            return max(0, $ini->diffInDays($fin) + 1);
        });

        $ocupacionPromedio = round(($roomNights / $capacidadTotal) * 100, 1);

        // Serie de ingresos por día (gráfico de línea)
        $ingresosPorDiaRaw = Pago::whereBetween('fecha_pago', [$desde, $hasta])
            ->selectRaw('DATE(fecha_pago) as fecha, SUM(monto) as monto')
            ->groupBy('fecha')
            ->get()
            ->keyBy('fecha');

        $serieIngresos = [];
        $cursor = $desde->copy();
        while ($cursor->lte($hasta)) {
            $clave = $cursor->toDateString();
            $serieIngresos[] = [
                'fecha' => $cursor->format('d/m'),
                'monto' => (float) ($ingresosPorDiaRaw[$clave]->monto ?? 0),
            ];
            $cursor->addDay();
        }

        // Ingresos por método de pago (gráfico de dona)
        $ingresosPorMetodo = Pago::whereBetween('fecha_pago', [$desde, $hasta])
            ->join('metodos_pago', 'metodos_pago.id', '=', 'pagos.metodo_id')
            ->selectRaw('metodos_pago.nombre as metodo, SUM(pagos.monto) as monto')
            ->groupBy('metodos_pago.nombre')
            ->orderByDesc('monto')
            ->get();

        // Ingresos por tipo de pago (adelanto / pago final / extensión / ingreso temprano)
        $ingresosPorTipoPago = Pago::whereBetween('fecha_pago', [$desde, $hasta])
            ->join('tipos_pago', 'tipos_pago.id', '=', 'pagos.tipo_id')
            ->selectRaw('tipos_pago.nombre as tipo, SUM(pagos.monto) as monto')
            ->groupBy('tipos_pago.nombre')
            ->orderByDesc('monto')
            ->get();

        // Top 5 habitaciones más rentables (según entrada dentro del rango)
        $topHabitaciones = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->whereBetween('reservas.fecha_entrada', [$desde, $hasta])
            ->selectRaw('
                reserva_habitaciones.habitacion_numero as numero,
                reserva_habitaciones.tipo_nombre_historico as tipo,
                SUM(reserva_habitaciones.precio_aplicado) as ingresos,
                COUNT(*) as reservas
            ')
            ->groupBy('reserva_habitaciones.habitacion_numero', 'reserva_habitaciones.tipo_nombre_historico')
            ->orderByDesc('ingresos')
            ->limit(5)
            ->get();

        // Ranking por tipo de habitación
        $topTipos = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->whereBetween('reservas.fecha_entrada', [$desde, $hasta])
            ->selectRaw('
                reserva_habitaciones.tipo_nombre_historico as tipo,
                SUM(reserva_habitaciones.precio_aplicado) as ingresos,
                COUNT(*) as reservas
            ')
            ->groupBy('reserva_habitaciones.tipo_nombre_historico')
            ->orderByDesc('ingresos')
            ->get();

        // Top 5 huéspedes más frecuentes (por reservas creadas en el rango)
        $topHuespedes = Reserva::whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('huesped_principal, COUNT(*) as reservas, SUM(costo_total) as gasto')
            ->groupBy('huesped_principal')
            ->orderByDesc('reservas')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                $huesped = Huesped::where('num_doc', $r->huesped_principal)->first();
                return [
                    'nombre'   => $huesped->nombre ?? $r->huesped_principal,
                    'num_doc'  => $r->huesped_principal,
                    'reservas' => (int) $r->reservas,
                    'gasto'    => (float) $r->gasto,
                ];
            });

        // Distribución horas vs noches
        $distribucionEstadia = Reserva::whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('es_por_horas, COUNT(*) as total')
            ->groupBy('es_por_horas')
            ->get()
            ->reduce(function ($acc, $r) {
                $acc[$r->es_por_horas ? 'horas' : 'noches'] = (int) $r->total;
                return $acc;
            }, ['horas' => 0, 'noches' => 0]);

        return [
            'rango' => [
                'desde' => $desde->format('Y-m-d'),
                'hasta' => $hasta->format('Y-m-d'),
            ],
            'kpis' => [
                'ingresos_netos'     => $ingresosNetos,
                'reservas_nuevas'    => $reservasNuevas,
                'ocupacion_promedio' => $ocupacionPromedio,
                'ticket_promedio'    => $ticketPromedio,
                'tasa_cancelacion'   => $tasaCancelacion,
            ],
            'ingresos_por_dia'      => $serieIngresos,
            'ingresos_por_metodo'   => $ingresosPorMetodo,
            'ingresos_por_tipo_pago' => $ingresosPorTipoPago,
            'top_habitaciones'      => $topHabitaciones,
            'top_tipos'             => $topTipos,
            'top_huespedes'         => $topHuespedes,
            'distribucion_estadia'  => $distribucionEstadia,
        ];
    }

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->resolverRango($request);
        $metricas = $this->calcularMetricas($desde, $hasta);

        return view('reportes.index', compact('metricas'));
    }

    public function datos(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'required|date|before_or_equal:today',
            'hasta' => 'required|date|before_or_equal:today|after_or_equal:desde',
        ]);

        [$desde, $hasta] = $this->resolverRango($request);

        return response()->json($this->calcularMetricas($desde, $hasta));
    }
}
<?php
// Ubicación destino: app/Services/PrediccionService.php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Pago;
use App\Models\Devolucion;
use App\Models\Sugerencia;
use App\Models\Habitacion;
use App\Models\EstadoReserva;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PrediccionService
{
    // Límites de seguridad para los parámetros que llegan del front
    private const MESES_HISTORICO_MIN = 3;
    private const MESES_HISTORICO_MAX = 60;
    private const MESES_PROYECCION_MIN = 1;
    private const MESES_PROYECCION_MAX = 12;

    // A partir de cuántos meses de histórico se activa cada nivel de estacionalidad.
    // Con menos de 1 año no hay ni un ciclo completo -> solo tendencia (sin estacionalidad).
    // Con 1-2 años -> un armónico (una onda anual simple).
    // Con 2+ años -> dos armónicos (permite picos/valles más marcados y menos "redondeados").
    private const MESES_MIN_1_ARMONICO = 12;
    private const MESES_MIN_2_ARMONICOS = 24;

    // Stopwords en español (ya sin tildes, porque el texto se normaliza antes de filtrar)
    private const STOPWORDS = [
        'el','la','los','las','un','una','unos','unas','de','del','al','a','en','y','o','que',
        'con','por','para','se','su','sus','es','fue','ser','esta','este','estos','estas','lo',
        'muy','mas','pero','como','tambien','sin','no','si','le','les','nos','ya','me','mi','mis',
        'tu','tus','yo','ellos','ellas','nosotros','porque','cuando','donde','hay','han','ha',
        'son','soy','eres','estan','pues','todo','toda','todos','todas','solo','desde','hasta',
        'entre','sobre','tan','tanto','asi','nuestro','nuestra','les','fue','habia',
    ];

    public function generar(int $mesesHistorico = 24, int $mesesProyeccion = 6): array
    {
        $mesesHistorico = max(self::MESES_HISTORICO_MIN, min($mesesHistorico, self::MESES_HISTORICO_MAX));
        $mesesProyeccion = max(self::MESES_PROYECCION_MIN, min($mesesProyeccion, self::MESES_PROYECCION_MAX));

        $desde = $this->fechaInicioHistorico($mesesHistorico);
        $hayDatos = Pago::exists() || Reserva::exists();
        $mesesConDatos = $this->listaMeses($desde);

        return [
            'meta' => [
                'desde' => $desde->format('Y-m-d'),
                'hasta' => now()->format('Y-m-d'),
                'meses_historico_solicitados' => $mesesHistorico,
                'meses_historico_reales' => count($mesesConDatos),
                'meses_proyeccion' => $mesesProyeccion,
                'hay_datos' => $hayDatos,
            ],
            'ingresos'          => $this->proyeccionEstacional($this->serieIngresos($desde), 'Ingresos', $mesesProyeccion),
            'reservas'          => $this->proyeccionEstacional($this->serieReservas($desde), 'Reservas', $mesesProyeccion),
            'ocupacion'         => $this->proyeccionEstacional($this->serieOcupacion($desde), 'Ocupación', $mesesProyeccion, esPorcentaje: true),
            'ticket_promedio'   => $this->proyeccionEstacional($this->serieTicketPromedio($desde), 'Ticket promedio', $mesesProyeccion),
            'tasa_cancelacion'  => $this->proyeccionEstacional($this->serieCancelacion($desde), 'Tasa de cancelación', $mesesProyeccion, esPorcentaje: true),
            'pct_horas'         => $this->proyeccionEstacional($this->seriePctHoras($desde), 'Estadía por horas (%)', $mesesProyeccion, esPorcentaje: true),
            'sugerencias'       => $this->analisisSugerencias(),
        ];
    }

    // ============================================================
    // RANGO HISTÓRICO
    // ============================================================

    private function fechaInicioHistorico(int $mesesHistorico): Carbon
    {
        $primerPago = Pago::min('fecha_pago');
        $primeraReserva = Reserva::min('created_at');

        $candidatos = array_filter([$primerPago, $primeraReserva]);
        $inicioReal = empty($candidatos)
            ? now()->copy()->subMonths($mesesHistorico - 1)
            : Carbon::parse(min($candidatos));

        $topeVentana = now()->copy()->subMonths($mesesHistorico - 1)->startOfMonth();

        // No ir más atrás del tope solicitado, aunque existan datos más viejos.
        return $inicioReal->greaterThan($topeVentana) ? $inicioReal->startOfMonth() : $topeVentana;
    }

    private function listaMeses(Carbon $desde): array
    {
        $meses = [];
        $cursor = $desde->copy()->startOfMonth();
        $limite = now()->copy()->startOfMonth();

        while ($cursor->lte($limite)) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $meses;
    }

    // ============================================================
    // SERIES MENSUALES (histórico)
    // ============================================================

    private function serieIngresos(Carbon $desde): array
    {
        $pagos = Pago::where('fecha_pago', '>=', $desde)
            ->selectRaw("DATE_FORMAT(fecha_pago, '%Y-%m') as periodo, SUM(monto) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $devoluciones = Devolucion::where('fecha_devolucion', '>=', $desde)
            ->selectRaw("DATE_FORMAT(fecha_devolucion, '%Y-%m') as periodo, SUM(monto_devuelto) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $serie = [];
        foreach ($this->listaMeses($desde) as $mes) {
            $bruto = (float) ($pagos[$mes] ?? 0);
            $dev = (float) ($devoluciones[$mes] ?? 0);
            $serie[$mes] = round($bruto - $dev, 2);
        }

        return $serie;
    }

    private function serieReservas(Carbon $desde): array
    {
        $conteos = Reserva::where('created_at', '>=', $desde)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as periodo, COUNT(*) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $serie = [];
        foreach ($this->listaMeses($desde) as $mes) {
            $serie[$mes] = (int) ($conteos[$mes] ?? 0);
        }

        return $serie;
    }

    private function serieTicketPromedio(Carbon $desde): array
    {
        $ingresos = $this->serieIngresos($desde);
        $reservas = $this->serieReservas($desde);

        $serie = [];
        foreach ($this->listaMeses($desde) as $mes) {
            $serie[$mes] = $reservas[$mes] > 0
                ? round($ingresos[$mes] / $reservas[$mes], 2)
                : 0.0;
        }

        return $serie;
    }

    private function serieCancelacion(Carbon $desde): array
    {
        $idCancelada = EstadoReserva::where('nombre', 'cancelada')->value('id');

        $canceladas = Reserva::where('estado_id', $idCancelada)
            ->where('updated_at', '>=', $desde)
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as periodo, COUNT(*) as total")
            ->groupBy('periodo')->pluck('total', 'periodo');

        $nuevas = $this->serieReservas($desde);

        $serie = [];
        foreach ($this->listaMeses($desde) as $mes) {
            $c = (int) ($canceladas[$mes] ?? 0);
            $serie[$mes] = $nuevas[$mes] > 0 ? round(($c / $nuevas[$mes]) * 100, 1) : 0.0;
        }

        return $serie;
    }

    private function seriePctHoras(Carbon $desde): array
    {
        $filas = Reserva::where('created_at', '>=', $desde)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as periodo, es_por_horas, COUNT(*) as total")
            ->groupBy('periodo', 'es_por_horas')
            ->get();

        $horas = [];
        $totales = [];
        foreach ($filas as $f) {
            $totales[$f->periodo] = ($totales[$f->periodo] ?? 0) + $f->total;
            if ($f->es_por_horas) {
                $horas[$f->periodo] = ($horas[$f->periodo] ?? 0) + $f->total;
            }
        }

        $serie = [];
        foreach ($this->listaMeses($desde) as $mes) {
            $t = $totales[$mes] ?? 0;
            $h = $horas[$mes] ?? 0;
            $serie[$mes] = $t > 0 ? round(($h / $t) * 100, 1) : 0.0;
        }

        return $serie;
    }

    private function serieOcupacion(Carbon $desde): array
    {
        $totalHabitaciones = max(1, Habitacion::where('activo', 1)->count());
        $idsEstados = EstadoReserva::whereIn('nombre', ['activa', 'finalizada'])->pluck('id');

        $intervalos = DB::table('reserva_habitaciones')
            ->join('reservas', 'reservas.id', '=', 'reserva_habitaciones.reserva_id')
            ->whereIn('reservas.estado_id', $idsEstados)
            ->where('reserva_habitaciones.fecha_salida_efectiva', '>=', $desde)
            ->select(
                'reserva_habitaciones.habitacion_numero as habitacion',
                'reservas.fecha_entrada as entrada',
                'reserva_habitaciones.fecha_salida_efectiva as salida'
            )
            ->get();

        // Set de "habitacion|Y-m-d" realmente ocupados. Deduplicar por cuarto+día
        // es la clave: así varias reservas por horas en el mismo cuarto el mismo
        // día NO se suman entre sí, y la ocupación nunca puede pasar de 100%.
        $diasOcupados = [];

        foreach ($intervalos as $r) {
            $entradaFecha = Carbon::parse($r->entrada)->startOfDay();
            $salidaFecha  = Carbon::parse($r->salida)->startOfDay();

            if ($salidaFecha->lte($entradaFecha)) {
                // Por horas, no cruza medianoche: ocupa solo el día de entrada.
                $diasOcupados[$r->habitacion . '|' . $entradaFecha->format('Y-m-d')] = true;
                continue;
            }

            // Por noche(s): ocupa cada noche desde la entrada hasta el día
            // ANTERIOR a la salida (el día de checkout no es una noche ocupada).
            $cursor = $entradaFecha->copy();
            while ($cursor->lt($salidaFecha)) {
                $diasOcupados[$r->habitacion . '|' . $cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        // Agrupamos las claves ya deduplicadas por mes, una sola pasada.
        $ocupadosPorMes = [];
        foreach (array_keys($diasOcupados) as $clave) {
            $fecha = substr($clave, strpos($clave, '|') + 1);
            $mes = substr($fecha, 0, 7); // 'Y-m'
            $ocupadosPorMes[$mes] = ($ocupadosPorMes[$mes] ?? 0) + 1;
        }

        $serie = [];
        foreach ($this->listaMeses($desde) as $mes) {
            $diasMes = Carbon::createFromFormat('Y-m', $mes)->daysInMonth;
            $capacidad = max(1, $totalHabitaciones * $diasMes);
            $roomNights = $ocupadosPorMes[$mes] ?? 0;

            // min(100, ...) queda como red de seguridad adicional, aunque con la
            // deduplicación por cuarto+día ya no debería poder pasar de 100%.
            $serie[$mes] = round(min(100, ($roomNights / $capacidad) * 100), 1);
        }

        return $serie;
    }

    // ============================================================
    // MODELO DE PROYECCIÓN — regresión lineal + estacionalidad (Fourier)
    // ============================================================
    //
    // En vez de un solo modelo por indicador (regresión lineal simple,
    // promedio móvil o suavizamiento exponencial simple -- que solo
    // capturan nivel/tendencia y por eso la proyección salía plana),
    // se usa UN único modelo general para todos los indicadores:
    //
    //     y(t) = a + b*t + Σ [ c_h * sin(2π*h*t/12) + d_h * cos(2π*h*t/12) ]
    //
    // Es una regresión lineal múltiple donde, además del tiempo (t),
    // se agregan términos seno/coseno de periodo 12 (armónicos de
    // Fourier) que representan el ciclo anual. Los coeficientes se
    // resuelven por mínimos cuadrados (ecuaciones normales + eliminación
    // gaussiana), sin depender de librerías externas.
    //
    // La cantidad de armónicos se adapta a cuántos meses de histórico
    // hay disponibles, para no "inventar" estacionalidad cuando no hay
    // ni un ciclo completo de datos:
    //   - menos de 12 meses  -> 0 armónicos (cae a regresión lineal simple)
    //   - 12 a 23 meses      -> 1 armónico  (una onda anual)
    //   - 24+ meses          -> 2 armónicos (picos/valles más marcados)
    // ============================================================

    private function proyeccionEstacional(
        array $serie,
        string $etiqueta,
        int $mesesProyeccion,
        bool $esPorcentaje = false
    ): array {
        $valores = array_values($serie);
        $n = count($valores);

        if ($n < 2) {
            return $this->empaquetar($serie, [], 'Regresión con estacionalidad', $etiqueta);
        }

        $armonicos = $this->gradoArmonicosSegunHistorico($n);

        $filasX = [];
        $filasY = [];
        foreach ($valores as $t => $y) {
            $filasX[] = $this->vectorDiseno($t, $armonicos);
            $filasY[] = (float) $y;
        }

        $coeficientes = $this->minimosCuadrados($filasX, $filasY);

        $proyeccion = [];
        $ultimoMes = array_key_last($serie);
        $cursor = Carbon::createFromFormat('Y-m', $ultimoMes)->startOfMonth();

        for ($i = 1; $i <= $mesesProyeccion; $i++) {
            $cursor->addMonth();
            $t = $n - 1 + $i;
            $x = $this->vectorDiseno($t, $armonicos);

            $valor = 0.0;
            foreach ($x as $j => $xi) {
                $valor += $xi * $coeficientes[$j];
            }
            $valor = round($valor, 2);
            $valor = max(0, $valor);
            if ($esPorcentaje) {
                $valor = min(100, $valor);
            }

            $proyeccion[$cursor->format('Y-m')] = $valor;
        }

        $modelo = $armonicos > 0
            ? sprintf('Regresión con estacionalidad (Fourier, %d armónico%s)', $armonicos, $armonicos > 1 ? 's' : '')
            : 'Regresión lineal (histórico insuficiente para estimar estacionalidad)';

        return $this->empaquetar($serie, $proyeccion, $modelo, $etiqueta);
    }

    // Cuántos meses de histórico hacen falta para confiar en 1 o 2 armónicos.
    private function gradoArmonicosSegunHistorico(int $n): int
    {
        if ($n < self::MESES_MIN_1_ARMONICO) {
            return 0;
        }
        if ($n < self::MESES_MIN_2_ARMONICOS) {
            return 1;
        }
        return 2;
    }

    // Vector de variables independientes para el instante t:
    // [1, t, sin(2π*1*t/12), cos(2π*1*t/12), sin(2π*2*t/12), cos(2π*2*t/12), ...]
    private function vectorDiseno(int $t, int $armonicos): array
    {
        $x = [1.0, (float) $t];

        for ($h = 1; $h <= $armonicos; $h++) {
            $x[] = sin(2 * M_PI * $h * $t / 12);
            $x[] = cos(2 * M_PI * $h * $t / 12);
        }

        return $x;
    }

    // Mínimos cuadrados ordinarios vía ecuaciones normales: (XᵀX) β = Xᵀy
    private function minimosCuadrados(array $filasX, array $filasY): array
    {
        $k = count($filasX[0]);
        $n = count($filasX);

        $XtX = array_fill(0, $k, array_fill(0, $k, 0.0));
        $Xty = array_fill(0, $k, 0.0);

        for ($fila = 0; $fila < $n; $fila++) {
            $x = $filasX[$fila];
            $y = $filasY[$fila];

            for ($i = 0; $i < $k; $i++) {
                $Xty[$i] += $x[$i] * $y;
                for ($j = 0; $j < $k; $j++) {
                    $XtX[$i][$j] += $x[$i] * $x[$j];
                }
            }
        }

        return $this->resolverSistemaLineal($XtX, $Xty);
    }

    // Resuelve A·x = b por eliminación gaussiana con pivoteo parcial.
    // Si el sistema resulta (casi) singular en algún paso, esa incógnita
    // se deja en 0 en vez de dividir por un número casi cero.
    private function resolverSistemaLineal(array $A, array $b): array
    {
        $n = count($b);
        $EPS = 1e-10;

        for ($i = 0; $i < $n; $i++) {
            $maxFila = $i;
            $maxVal = abs($A[$i][$i]);
            for ($k = $i + 1; $k < $n; $k++) {
                if (abs($A[$k][$i]) > $maxVal) {
                    $maxVal = abs($A[$k][$i]);
                    $maxFila = $k;
                }
            }

            if ($maxFila !== $i) {
                [$A[$i], $A[$maxFila]] = [$A[$maxFila], $A[$i]];
                [$b[$i], $b[$maxFila]] = [$b[$maxFila], $b[$i]];
            }

            if (abs($A[$i][$i]) < $EPS) {
                continue; // fila casi singular, se resuelve como 0 más abajo
            }

            for ($k = $i + 1; $k < $n; $k++) {
                $factor = $A[$k][$i] / $A[$i][$i];
                for ($j = $i; $j < $n; $j++) {
                    $A[$k][$j] -= $factor * $A[$i][$j];
                }
                $b[$k] -= $factor * $b[$i];
            }
        }

        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $suma = $b[$i];
            for ($j = $i + 1; $j < $n; $j++) {
                $suma -= $A[$i][$j] * $x[$j];
            }
            $x[$i] = abs($A[$i][$i]) > $EPS ? $suma / $A[$i][$i] : 0.0;
        }

        return $x;
    }

    private function empaquetar(array $historico, array $proyeccion, string $modelo, string $etiqueta): array
    {
        return [
            'etiqueta' => $etiqueta,
            'modelo' => $modelo,
            'historico' => array_map(fn ($mes, $valor) => [
                'periodo' => $this->formatoPeriodo($mes),
                'valor' => $valor,
            ], array_keys($historico), array_values($historico)),
            'proyeccion' => array_map(fn ($mes, $valor) => [
                'periodo' => $this->formatoPeriodo($mes),
                'valor' => $valor,
            ], array_keys($proyeccion), array_values($proyeccion)),
        ];
    }

    private function formatoPeriodo(string $mes): string
    {
        return Carbon::createFromFormat('Y-m', $mes)->translatedFormat('M/y');
    }

    // ============================================================
    // MINERÍA DE TEXTO — SUGERENCIAS (unigramas + bigramas)
    // ============================================================

    private function analisisSugerencias(): array
    {
        $comentarios = Sugerencia::pluck('comentario');
        $total = $comentarios->count();

        $frecuencias = [];

        foreach ($comentarios as $comentario) {
            $palabras = $this->tokenizar($comentario);
            $n = count($palabras);

            foreach ($palabras as $palabra) {
                $frecuencias[$palabra] = ($frecuencias[$palabra] ?? 0) + 1;
            }

            for ($i = 0; $i < $n - 1; $i++) {
                $bigrama = $palabras[$i] . ' ' . $palabras[$i + 1];
                $frecuencias[$bigrama] = ($frecuencias[$bigrama] ?? 0) + 1;
            }
        }

        arsort($frecuencias);
        $top = array_slice($frecuencias, 0, 15, true);

        return [
            'total_registros' => $total,
            'ranking' => array_map(fn ($termino, $veces) => [
                'termino' => $termino,
                'veces' => $veces,
            ], array_keys($top), array_values($top)),
        ];
    }

    private function tokenizar(string $texto): array
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
        $palabras = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($palabras, function ($p) {
            return mb_strlen($p) > 2 && !in_array($p, self::STOPWORDS, true);
        }));
    }
}
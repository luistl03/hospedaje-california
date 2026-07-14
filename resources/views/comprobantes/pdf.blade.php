<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $comprobante->serie }}-{{ $comprobante->numero }}</title>
    <style>
        @page {
            margin: 4mm 5mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #000;
            margin: 0;
            width: 70mm;
        }
        .centro { text-align: center; }
        .logo { width: 26mm; margin-bottom: 4px; }
        .nombre { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
        .sub { font-size: 8.5px; margin-top: 1px; }

        .linea {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .linea-doble {
            border-top: 1px solid #000;
            margin: 6px 0;
        }

        .doc-tipo { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
        .doc-serie { font-size: 13px; font-weight: bold; margin-top: 2px; }

        table.filas { width: 100%; border-collapse: collapse; }
        table.filas td { padding: 1.5px 0; font-size: 9.5px; vertical-align: top; }
        .label { width: 36%; }
        .valor { width: 64%; text-align: right; }

        .seccion-titulo { font-size: 9px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }

        table.lista { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.lista td { padding: 1.5px 0; font-size: 9.5px; }
        .lista-principal { width: 62%; }
        .lista-secundaria { width: 38%; text-align: right; }
        .nota { font-size: 8px; color: #444; }

        .total-fila td { padding-top: 6px; font-size: 12px; font-weight: bold; }
        .subtotal-fila td { padding-top: 3px; font-size: 9.5px; }

        .pie {
            margin-top: 10px;
            font-size: 7.5px;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <div class="centro">
        <img class="logo" src="{{ public_path('images/isologo_california.png') }}" alt="Hospedaje California">
        <div class="nombre">HOSPEDAJE CALIFORNIA</div>
        <div class="sub">Comprobante de pago interno</div>
    </div>

    <div class="linea-doble"></div>

    <div class="centro">
        <div class="doc-tipo">{{ $comprobante->tipo->nombre }}</div>
        <div class="doc-serie">{{ $comprobante->serie }}-{{ $comprobante->numero }}</div>
    </div>

    <div class="linea"></div>

    @php
        // Consolida monto y tiempo de extensiones por número de habitación
        $extensionesPorHabitacion = [];
        foreach ($reserva->extensiones as $ext) {
            foreach ($ext->habitaciones as $hab) {
                if (!isset($extensionesPorHabitacion[$hab->numero])) {
                    $extensionesPorHabitacion[$hab->numero] = ['monto' => 0, 'cantidad' => 0];
                }
                $extensionesPorHabitacion[$hab->numero]['monto']    += (float) $hab->pivot->monto;
                $extensionesPorHabitacion[$hab->numero]['cantidad'] += $ext->cantidad;
            }
        }

        // Tiempo total (original + extensión) por habitación
        $tiempoTotalPorHabitacion = $reserva->habitaciones->mapWithKeys(function ($hab) use ($extensionesPorHabitacion) {
            $extra = $extensionesPorHabitacion[$hab->numero]['cantidad'] ?? 0;
            return [$hab->numero => $hab->pivot->tiempo_estadia + $extra];
        });

        // Para la fila-resumen "Estadía": el máximo entre todas las habitaciones
        $estadiaMaxima = $tiempoTotalPorHabitacion->max();
    @endphp

    <table class="filas">
        <tr>
            <td class="label">Fecha emisión</td>
            <td class="valor">{{ $comprobante->fecha_emision->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Reserva</td>
            <td class="valor">#{{ $reserva->id }}</td>
        </tr>
        <tr>
            <td class="label">Check-in</td>
            <td class="valor">{{ $reserva->fecha_entrada->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Check-out</td>
            <td class="valor">{{ $reserva->fecha_salida->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Estadía</td>
            <td class="valor">
                @if($reserva->es_por_horas)
                    {{ $estadiaMaxima }} hora(s)
                @else
                    {{ $estadiaMaxima }} noche(s)
                @endif
            </td>
        </tr>
    </table>

    <div class="linea"></div>

    <div class="seccion-titulo">Habitaciones</div>
    <table class="lista">
        @foreach($reserva->habitaciones as $hab)
            @php
                $montoExtra  = $extensionesPorHabitacion[$hab->numero]['monto'] ?? 0;
                $montoTotal  = $hab->pivot->precio_aplicado + $montoExtra;
                $tiempoTotal = $tiempoTotalPorHabitacion[$hab->numero];
                $unidadTotal = $reserva->es_por_horas
                    ? $tiempoTotal . 'h'
                    : $tiempoTotal . ' ' . ($tiempoTotal == 1 ? 'noche' : 'noches');
            @endphp
            <tr>
                <td class="lista-principal">N° {{ $hab->numero }} — {{ $hab->pivot->tipo_nombre_historico }} ({{ $unidadTotal }})</td>
                <td class="lista-secundaria">S/ {{ number_format($montoTotal, 2) }}</td>
            </tr>
        @endforeach
    </table>   

    <div class="linea"></div>

    <div class="seccion-titulo">Huéspedes</div>
    <table class="lista">
        @foreach($reserva->huespedes as $h)
            <tr>
                <td class="lista-principal">
                    {{ $h->nombre }}
                    @if($h->num_doc === $reserva->huesped_principal) <span class="nota">(Principal)</span> @endif
                </td>
                <td class="lista-secundaria">{{ $h->num_doc }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linea"></div>

    <div class="seccion-titulo">Cliente</div>
    <table class="filas">
        @if($comprobante->ruc)
            <tr>
                <td class="label">RUC</td>
                <td class="valor">{{ $comprobante->ruc }}</td>
            </tr>
            <tr>
                <td class="label">Razón social</td>
                <td class="valor">{{ $comprobante->razon_social }}</td>
            </tr>
        @else
            <tr>
                <td class="label">Nombre</td>
                <td class="valor">{{ $huespedPrincipal->nombre ?? $reserva->huesped_principal }}</td>
            </tr>
            <tr>
                <td class="label">Documento</td>
                <td class="valor">{{ $huespedPrincipal->num_doc ?? $reserva->huesped_principal }}</td>
            </tr>
        @endif
    </table>

    <div class="linea"></div>

    <div class="seccion-titulo">Pagos registrados</div>
    <table class="lista">
        @foreach($reserva->pagos as $p)
            <tr>
                <td class="lista-principal">{{ ucfirst($p->tipo->nombre) }}</td>
                <td class="lista-secundaria">S/ {{ number_format($p->monto, 2) }}</td>
            </tr>
            <tr>
                <td class="lista-principal nota">
                    {{ ucfirst($p->metodo->nombre) }}
                    @if($p->numero_operacion) — Op. {{ $p->numero_operacion }} @endif
                </td>
                <td class="lista-secundaria"></td>
            </tr>
        @endforeach
    </table>

    @if($reserva->devoluciones->isNotEmpty())
        <div class="linea"></div>
        <div class="seccion-titulo">Devoluciones</div>
        <table class="lista">
            @foreach($reserva->devoluciones as $d)
                <tr>
                    <td class="lista-principal">
                        Devuelto
                        <span class="nota">({{ $d->origen === 'ajuste fechas' ? 'ajuste de fechas' : 'cancelación' }})</span>
                    </td>
                    <td class="lista-secundaria">S/ {{ number_format($d->monto_devuelto, 2) }}</td>
                </tr>
                <tr>
                    <td class="lista-principal nota">
                        {{ ucfirst($d->metodo) }}
                        @if($d->numero_operacion) — Op. {{ $d->numero_operacion }} @endif
                    </td>
                    <td class="lista-secundaria"></td>
                </tr>
                @if($d->monto_retenido > 0)
                    <tr>
                        <td class="lista-principal nota">Retenido (gastos administrativos)</td>
                        <td class="lista-secundaria">S/ {{ number_format($d->monto_retenido, 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    <div class="linea-doble"></div>

    <table class="filas">
        <tr class="subtotal-fila">
            <td class="label">Total reserva</td>
            <td class="valor">S/ {{ number_format($reserva->costo_total, 2) }}</td>
        </tr>
        <tr class="total-fila">
            <td class="label">TOTAL PAGADO (NETO)</td>
            <td class="valor">S/ {{ number_format($reserva->pagos->sum('monto') - $reserva->devoluciones->sum(fn($d) 
                => $d->monto_devuelto + $d->monto_retenido), 2) }}</td>
        </tr>
    </table>

    <div class="linea"></div>

    <table class="filas">
        <tr>
            <td class="label">Atendido por</td>
            <td class="valor">{{ $reserva->usuario->name }}</td>
        </tr>
    </table>

    <div class="linea"></div>

    <div class="pie">
        Comprobante interno de gestión.<br>
        No válido como documento tributario SUNAT.<br>
        {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
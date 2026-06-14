<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reserva;
use App\Models\Habitacion;
use App\Models\EstadoReserva;
use App\Models\EstadoHabitacion;
use Carbon\Carbon;

class MarcarHabitacionesReservadas extends Command
{
    protected $signature   = 'reservas:marcar-reservadas';
    protected $description = 'Marca como reservada las habitaciones cuya entrada es en ≤30 min';

    public function handle(): void
    {
        $idPendiente  = EstadoReserva::where('nombre', 'pendiente')->value('id');
        $idDisponible = EstadoHabitacion::where('nombre', 'disponible')->value('id');
        $idReservada  = EstadoHabitacion::where('nombre', 'reservada')->value('id');

        $ahora      = Carbon::now();
        $limite     = $ahora->copy()->addMinutes(30);

        // Reservas pendientes cuya entrada cae entre ahora y +30 min
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

        $this->info("Habitaciones marcadas como reservadas: {$conteo}");
    }
}
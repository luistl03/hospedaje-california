<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'reserva_id',
        'origen',
        'monto_devuelto',
        'monto_retenido',
        'metodo',
        'numero_operacion',
        'fecha_devolucion',
    ];
}
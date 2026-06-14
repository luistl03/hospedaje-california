<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoReserva extends Model
{
    protected $table = 'estados_reserva';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'estado_id');
    }
}
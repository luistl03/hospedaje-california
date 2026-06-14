<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoHabitacion extends Model
{
    protected $table = 'estados_habitacion';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    public function habitaciones()
    {
        return $this->hasMany(Habitacion::class, 'estado_id');
    }
}
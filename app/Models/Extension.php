<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    use HasFactory;

    protected $table = 'extensiones';

    protected $fillable = [
        'reserva_id',
        'cantidad',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function habitaciones()
    {
        return $this->belongsToMany(
            Habitacion::class,
            'extension_habitaciones',
            'extension_id',
            'numero_habitacion',
            'id',
            'numero'
        )->withPivot('monto', 'reserva_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'extension_id');
    }
}
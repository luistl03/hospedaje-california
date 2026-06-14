<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'usuario_id',
        'tipo_estadia_id',
        'fecha_entrada',
        'fecha_salida',
        'estado_id',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrada' => 'datetime',
            'fecha_salida'  => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estado()
    {
        return $this->belongsTo(EstadoReserva::class, 'estado_id');
    }

    public function tipoEstadia()
    {
        return $this->belongsTo(TipoEstadia::class, 'tipo_estadia_id');
    }

    public function huespedes()
    {
        return $this->belongsToMany(Huesped::class, 'reserva_huespedes', 'reserva_id', 'huesped_id');
    }

    public function habitaciones()
    {
        return $this->belongsToMany(
            Habitacion::class,
            'reserva_habitaciones',
            'reserva_id',
            'habitacion_numero',
            'id',
            'numero'
        )->withPivot('precio_aplicado', 'horas');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'reserva_id');
    }

    public function extensiones()
    {
        return $this->hasMany(Extension::class, 'reserva_id');
    }
}
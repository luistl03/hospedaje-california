<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habitacion extends Model
{
    use HasFactory;

    protected $table = 'habitaciones';
    protected $primaryKey = 'numero';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'numero',
        'tipo_id',
        'estado_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'numero'    => 'integer',
            'estado_id' => 'integer',
            'activo'    => 'boolean',
        ];
    }

    public function tipo()
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_id');
    }

    public function estado()
    {
        return $this->belongsTo(EstadoHabitacion::class, 'estado_id');
    }

    public function reservas()
    {
        return $this->belongsToMany(Reserva::class, 'reserva_habitaciones', 'habitacion_numero', 'reserva_id', 'numero', 'id');
    }

    public function extensiones()
    {
        return $this->belongsToMany(Extension::class, 'extension_habitaciones', 'habitacion_numero', 'extension_id', 'numero', 'id');
    }
}
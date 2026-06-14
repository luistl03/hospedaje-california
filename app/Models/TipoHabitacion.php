<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'precio_hora', 'precio_noche', 'descripcion', 'max_huespedes', 'activo'])]
class TipoHabitacion extends Model
{
    use HasFactory;

    protected $table = 'tipos_habitacion';

    protected function casts(): array
    {
        return [
            'precio_hora'  => 'decimal:2',
            'precio_noche' => 'decimal:2',
            'max_huespedes'=> 'integer',
            'activo'       => 'boolean',
        ];
    }

    public function habitaciones()
    {
        return $this->hasMany(Habitacion::class, 'tipo_id');
    }
}
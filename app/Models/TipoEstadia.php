<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEstadia extends Model
{
    protected $table = 'tipos_estadia';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'tipo_estadia_id');
    }
}
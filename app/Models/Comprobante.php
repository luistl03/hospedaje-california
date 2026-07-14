<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    use HasFactory;

    protected $table = 'comprobantes';

    protected $fillable = [
        'serie',
        'numero',
        'fecha_emision',
        'tipo_id',
        'ruc',
        'razon_social',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
        ];
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'comprobante_id');
    }

    public function tipo()
    {
        return $this->belongsTo(TipoComprobante::class, 'tipo_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Huesped extends Model
{
    use HasFactory;

    protected $table = 'huespedes';

    protected $fillable = [
        'nombre',
        'tipo_doc_id',
        'num_doc',
        'telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_doc_id' => 'integer',
            'activo'      => 'boolean',
        ];
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_doc_id');
    }

    public function reservas()
    {
        return $this->belongsToMany(Reserva::class, 'reserva_huespedes', 'huesped_id', 'reserva_id');
    }
}
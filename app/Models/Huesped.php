<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Huesped extends Model
{
    use HasFactory;

    protected $table = 'huespedes';

    protected $primaryKey = 'num_doc';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'num_doc',
        'nombre',
        'telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function reservas()
    {
        return $this->belongsToMany(
            Reserva::class,
            'reserva_huespedes',
            'huesped_num_doc',
            'reserva_id',
            'num_doc',
            'id'
        );
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'reserva_id',
        'usuario_id',
        'extension_id',
        'monto',
        'metodo_id',
        'tipo_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_id');
    }

    public function tipo()
    {
        return $this->belongsTo(TipoPago::class, 'tipo_id');
    }
}
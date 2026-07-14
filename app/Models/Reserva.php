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
        'huesped_principal',
        'comprobante_id',
        'fecha_entrada',
        'fecha_salida',
        'estado_id',
        'es_por_horas',
        'costo_total',
        'saldo_pendiente',
        'monto_recargo', 
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrada'   => 'datetime',
            'fecha_salida'    => 'datetime',
            'es_por_horas'    => 'boolean',
            'costo_total'     => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
            'monto_recargo'   => 'decimal:2',
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

    public function huespedes()
    {
        return $this->belongsToMany(
            Huesped::class,
            'reserva_huespedes',
            'reserva_id',        // FK local en la tabla pivote
            'huesped_num_doc',   // FK relacionada en la tabla pivote
            'id',                // PK local de Reserva
            'num_doc'            // PK de Huesped
        );
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
        )->withPivot('precio_aplicado', 'tiempo_estadia', 'fecha_salida_efectiva', 'tipo_nombre_historico');
    }
    
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'reserva_id');
    }

    public function extensiones()
    {
        return $this->hasMany(Extension::class, 'reserva_id');
    }

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }

    public function devoluciones()
    {
        return $this->hasMany(Devolucion::class);
    }
}
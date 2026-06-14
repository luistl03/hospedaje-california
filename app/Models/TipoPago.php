<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPago extends Model
{
    protected $table = 'tipos_pago';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'tipo_id');
    }
}
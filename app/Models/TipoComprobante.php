<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    protected $table = 'tipos_comprobante';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    public function comprobantes()
    {
        return $this->hasMany(Comprobante::class, 'tipo_id');
    }
}
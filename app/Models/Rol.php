<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    public function users()
    {
        return $this->hasMany(User::class, 'rol_id');
    }
}
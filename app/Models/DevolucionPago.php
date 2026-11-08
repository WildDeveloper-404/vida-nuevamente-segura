<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionPago extends Model
{
    protected $table = 'devolucion_pago';
    protected $primaryKey = 'id_pago';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id_pago', 'estado', 'referencia', 'actualizada_en'];

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago');
    }
}

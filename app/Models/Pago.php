<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Pago extends Model
{
    protected $table = 'pago';
    protected $primaryKey = 'id_pago';
    public $timestamps = false;
    
    protected $fillable = ['id_pago', 'id_cita', 'monto', 'metodo_pago', 'referencia_stripe', 'estado_pago', 'fecha_pago'];
    protected $casts = ['monto'=>'decimal:2'];
    public function cita() { return $this->belongsTo(CitaMedica::class, 'id_cita'); } public function comprobante() { return $this->hasOne(Comprobante::class, 'id_pago'); }
}

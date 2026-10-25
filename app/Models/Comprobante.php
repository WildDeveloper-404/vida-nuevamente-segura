<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Comprobante extends Model
{
    protected $table = 'comprobante';
    protected $primaryKey = 'id_comprobante';
    public $timestamps = false;
    
    protected $fillable = ['id_comprobante', 'id_pago', 'nro_comprobante', 'nit_ci', 'razon_social', 'concepto', 'fecha_emision'];
    
    public function pago() { return $this->belongsTo(Pago::class, 'id_pago'); }
    
}

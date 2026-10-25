<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Bitacora extends Model
{
    protected $table = 'bitacora';
    protected $primaryKey = 'id_bitacora';
    public $timestamps = false;
    
    protected $fillable = ['id_bitacora', 'id_usuario', 'modulo', 'accion', 'descripcion', 'direccion_ip', 'fecha_hora'];
    
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    
}

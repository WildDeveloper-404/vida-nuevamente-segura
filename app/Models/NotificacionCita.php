<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificacionCita extends Model
{
    protected $table = 'notificacion_cita';
    protected $primaryKey = 'id_notificacion';
    public $timestamps = false;
    
    protected $fillable = ['id_notificacion', 'id_cita', 'tipo', 'mensaje', 'correo_destino', 'estado_envio', 'fecha_envio'];
    
    public function cita() { return $this->belongsTo(CitaMedica::class, 'id_cita'); }
    
}

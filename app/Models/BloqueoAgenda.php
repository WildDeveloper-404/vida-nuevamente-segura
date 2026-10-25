<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BloqueoAgenda extends Model
{
    protected $table = 'bloqueo_agenda';
    protected $primaryKey = 'id_bloqueo';
    public $timestamps = false;
    
    protected $fillable = ['id_bloqueo', 'id_medico', 'fecha_bloqueo', 'hora_inicio', 'hora_fin', 'tipo_bloqueo', 'motivo', 'fecha_registro'];
    
    public function medico() { return $this->belongsTo(Medico::class, 'id_medico'); }
    
}

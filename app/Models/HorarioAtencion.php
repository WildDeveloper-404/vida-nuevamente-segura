<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HorarioAtencion extends Model
{
    protected $table = 'horario_atencion';
    protected $primaryKey = 'id_horario';
    public $timestamps = false;
    
    protected $fillable = ['id_horario', 'id_medico', 'id_consultorio', 'dia_semana', 'hora_inicio', 'hora_fin', 'duracion_minutos', 'cupo_maximo', 'estado'];
    
    public function medico() { return $this->belongsTo(Medico::class, 'id_medico'); }
    public function consultorio() { return $this->belongsTo(Consultorio::class, 'id_consultorio'); }
    
}

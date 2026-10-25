<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CitaMedica extends Model
{
    protected $table = 'cita_medica';
    protected $primaryKey = 'id_cita';
    public $timestamps = false;
    
    protected $fillable = ['id_cita', 'codigo_cita', 'id_paciente', 'id_medico', 'id_especialidad', 'id_cita_anterior', 'fecha_cita', 'hora_inicio', 'hora_fin', 'motivo_consulta', 'estado', 'motivo_cancelacion', 'fecha_registro'];
    
    public function paciente() { return $this->belongsTo(Paciente::class, 'id_paciente'); } public function medico() { return $this->belongsTo(Medico::class, 'id_medico'); } public function especialidad() { return $this->belongsTo(Especialidad::class, 'id_especialidad'); } public function pago() { return $this->hasOne(Pago::class, 'id_cita'); } public function receta() { return $this->hasOne(RecetaMedica::class, 'id_cita'); } public function anterior() { return $this->belongsTo(self::class, 'id_cita_anterior'); } public function reprogramaciones() { return $this->hasMany(self::class, 'id_cita_anterior'); } public function derivaciones() { return $this->hasMany(DerivacionMedica::class, 'id_cita_origen'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Medico extends Model
{
    protected $table = 'medico';
    protected $primaryKey = 'id_medico';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id_medico', 'codigo_medico', 'nro_matricula', 'estado'];
    
    public function persona() { return $this->belongsTo(Persona::class, 'id_medico'); } public function horarios() { return $this->hasMany(HorarioAtencion::class, 'id_medico'); } public function especialidades() { return $this->belongsToMany(Especialidad::class, 'medico_especialidad', 'id_medico', 'id_especialidad')->withPivot('es_principal','fecha_certificacion'); } public function citas() { return $this->hasMany(CitaMedica::class, 'id_medico'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DerivacionMedica extends Model
{
    protected $table = 'derivacion_medica';
    protected $primaryKey = 'id_derivacion';
    public $timestamps = false;
    
    protected $fillable = ['id_derivacion', 'codigo_derivacion', 'id_cita_origen', 'id_especialidad_destino', 'prioridad', 'motivo_clinico', 'estado', 'fecha_emision'];
    
    public function citaOrigen() { return $this->belongsTo(CitaMedica::class, 'id_cita_origen'); }
    public function especialidadDestino() { return $this->belongsTo(Especialidad::class, 'id_especialidad_destino'); }
    
}

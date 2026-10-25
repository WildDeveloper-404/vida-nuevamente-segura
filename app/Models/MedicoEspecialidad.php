<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;
class MedicoEspecialidad extends Pivot
{
    use \App\Models\Concerns\CompositePivot;
    protected $compositeKeys = ['id_medico','id_especialidad'];
    protected $table = 'medico_especialidad';
    protected $primaryKey = 'id_medico';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id_medico', 'id_especialidad', 'es_principal', 'fecha_certificacion'];
    
    public function medico() { return $this->belongsTo(Medico::class, 'id_medico'); }
    public function especialidad() { return $this->belongsTo(Especialidad::class, 'id_especialidad'); }
    
}

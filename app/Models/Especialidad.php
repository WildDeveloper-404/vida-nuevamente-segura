<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Especialidad extends Model
{
    protected $table = 'especialidad';
    protected $primaryKey = 'id_especialidad';
    public $timestamps = false;
    
    protected $fillable = ['id_especialidad', 'id_especialidad_padre', 'nombre', 'nombre_normalizado', 'descripcion', 'edad_minima', 'edad_maxima', 'arancel_consulta', 'estado'];
    protected $casts = ['arancel_consulta'=>'decimal:2'];
    public function padre() { return $this->belongsTo(self::class, 'id_especialidad_padre'); } public function subespecialidades() { return $this->hasMany(self::class, 'id_especialidad_padre')->with('subespecialidades'); } public function medicos() { return $this->belongsToMany(Medico::class, 'medico_especialidad', 'id_especialidad', 'id_medico'); }
}

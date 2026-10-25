<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;
class RolPermiso extends Pivot
{
    use \App\Models\Concerns\CompositePivot;
    protected $compositeKeys = ['id_rol','id_permiso'];
    protected $table = 'rol_permiso';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id_rol', 'id_permiso', 'fecha_asignacion'];
    
    public function rol() { return $this->belongsTo(Rol::class, 'id_rol'); }
    public function permiso() { return $this->belongsTo(Permiso::class, 'id_permiso'); }
    
}

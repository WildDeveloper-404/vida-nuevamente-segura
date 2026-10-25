<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Rol extends Model
{
    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;
    
    protected $fillable = ['id_rol', 'nombre_rol', 'descripcion'];
    
    public function permisos() { return $this->belongsToMany(Permiso::class, 'rol_permiso', 'id_rol', 'id_permiso')->withPivot('fecha_asignacion'); } public function usuarios() { return $this->hasMany(Usuario::class, 'id_rol'); }
}

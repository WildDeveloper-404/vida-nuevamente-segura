<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Permiso extends Model
{
    protected $table = 'permiso';
    protected $primaryKey = 'id_permiso';
    public $timestamps = false;
    
    protected $fillable = ['id_permiso', 'nombre_permiso', 'id_modulo'];
    
    public function modulo() { return $this->belongsTo(Modulo::class, 'id_modulo'); } public function roles() { return $this->belongsToMany(Rol::class, 'rol_permiso', 'id_permiso', 'id_rol'); }
}

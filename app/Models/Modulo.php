<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Modulo extends Model
{
    protected $table = 'modulo';
    protected $primaryKey = 'id_modulo';
    public $timestamps = false;
    
    protected $fillable = ['id_modulo', 'nombre_modulo'];
    
    public function permisos() { return $this->hasMany(Permiso::class, 'id_modulo'); }
}

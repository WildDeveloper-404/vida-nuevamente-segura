<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Usuario extends Model
{
    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;
    
    protected $fillable = ['id_usuario', 'id_persona', 'correo', 'password_hash', 'estado', 'intentos_fallidos', 'bloqueado_hasta', 'token_recuperacion', 'token_expiracion', 'ultimo_acceso', 'id_rol', 'fecha_registro'];
    protected $casts = ['bloqueado_hasta'=>'datetime','token_expiracion'=>'datetime'];
    protected $hidden = ['password_hash','token_recuperacion']; public function persona() { return $this->belongsTo(Persona::class, 'id_persona'); } public function rol() { return $this->belongsTo(Rol::class, 'id_rol'); }
}

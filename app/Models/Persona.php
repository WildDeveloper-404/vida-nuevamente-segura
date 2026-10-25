<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Persona extends Model
{
    protected $table = 'persona';
    protected $primaryKey = 'id_persona';
    public $timestamps = false;
    
    protected $fillable = ['id_persona', 'ci', 'nombres', 'apellidos', 'telefono', 'direccion'];
    
    public function usuario() { return $this->hasOne(Usuario::class, 'id_persona'); } public function paciente() { return $this->hasOne(Paciente::class, 'id_paciente'); } public function medico() { return $this->hasOne(Medico::class, 'id_medico'); } public function getNombreCompletoAttribute(): string { return $this->nombres.' '.$this->apellidos; }
}

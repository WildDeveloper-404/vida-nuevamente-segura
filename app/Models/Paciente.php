<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Paciente extends Model
{
    protected $table = 'paciente';
    protected $primaryKey = 'id_paciente';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id_paciente', 'codigo_paciente', 'sexo', 'fecha_nacimiento', 'estado', 'fecha_registro'];
    
    public function persona() { return $this->belongsTo(Persona::class, 'id_paciente'); } public function perfilClinico() { return $this->hasOne(PerfilClinico::class, 'id_paciente'); } public function citas() { return $this->hasMany(CitaMedica::class, 'id_paciente'); } public function getEdadAttribute(): int { return (int) \Carbon\Carbon::parse($this->fecha_nacimiento)->age; }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PerfilClinico extends Model
{
    protected $table = 'perfil_clinico';
    protected $primaryKey = 'id_perfil';
    public $timestamps = false;
    
    protected $fillable = ['id_perfil', 'id_paciente', 'tipo_sangre', 'alergias', 'enfermedades_base', 'medicamentos_habituales', 'antecedentes_relevantes', 'contacto_emergencia_nombre', 'contacto_emergencia_parentesco', 'contacto_emergencia_telefono', 'ultima_actualizacion'];
    
    public function paciente() { return $this->belongsTo(Paciente::class, 'id_paciente'); }
    
}

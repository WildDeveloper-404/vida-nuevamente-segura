<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConsultaAsistenteIA extends Model
{
    protected $table = 'consulta_asistente_ia';
    protected $primaryKey = 'id_consulta_ia';
    public $timestamps = false;
    
    protected $fillable = ['id_consulta_ia', 'id_usuario', 'canal', 'texto_entrada', 'sql_generado', 'respuesta_emitida', 'fecha_hora'];
    
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecetaMedica extends Model
{
    protected $table = 'receta_medica';
    protected $primaryKey = 'id_receta';
    public $timestamps = false;
    
    protected $fillable = ['id_receta', 'id_cita', 'diagnostico', 'cie10', 'codigo_qr', 'huella_integridad', 'fecha_emision'];
    
    public function cita() { return $this->belongsTo(CitaMedica::class, 'id_cita'); } public function detalles() { return $this->hasMany(DetalleReceta::class, 'id_receta'); }
}

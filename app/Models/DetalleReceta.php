<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DetalleReceta extends Model
{
    protected $table = 'detalle_receta';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;
    
    protected $fillable = ['id_detalle', 'id_receta', 'tipo_item', 'medicamento_o_examen', 'dosis', 'frecuencia', 'duracion'];
    
    public function receta() { return $this->belongsTo(RecetaMedica::class, 'id_receta'); }
    
}

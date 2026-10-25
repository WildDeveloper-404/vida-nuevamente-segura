<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonalAdministrativo extends Model
{
    protected $table = 'personal_administrativo';
    protected $primaryKey = 'id_personal';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id_personal', 'cargo', 'estado'];
    
    public function persona() { return $this->belongsTo(Persona::class, 'id_personal'); }
    
}

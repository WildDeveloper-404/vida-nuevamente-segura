<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Consultorio extends Model
{
    protected $table = 'consultorio';
    protected $primaryKey = 'id_consultorio';
    public $timestamps = false;
    
    protected $fillable = ['id_consultorio', 'numero', 'bloque', 'piso', 'estado'];
    
    
}

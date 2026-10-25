<?php
namespace App\Http\Controllers;
use App\Models\Especialidad;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EspecialidadController extends Controller
{
    public function index(Request $r) { return view('admin.especialidades.index',['especialidades'=>Especialidad::orderBy('nombre')->get(),'edit'=>$r->filled('editar')?Especialidad::findOrFail($r->integer('editar')):null]); }
    public function store(Request $r) { return $this->save($r,new Especialidad); }
    public function update(Request $r,Especialidad $especialidad) { return $this->save($r,$especialidad); }
    private function save(Request $r,Especialidad $e)
    {
        $d=$r->validate(['nombre'=>'required|string|max:100','descripcion'=>'nullable|string|max:250','edad_minima'=>'required|integer|min:0|max:120','edad_maxima'=>'required|integer|gte:edad_minima|max:120','arancel_consulta'=>'required|numeric|min:0|max:99999999.99','id_especialidad_padre'=>'nullable|integer|exists:especialidad,id_especialidad','estado'=>'required|in:Activo,Inactivo']);
        $parent=$d['id_especialidad_padre']??null; $seen=[];
        while($parent) { if($parent==$e->id_especialidad || isset($seen[$parent])) throw ValidationException::withMessages(['id_especialidad_padre'=>'No se permiten ciclos entre especialidades.']);$seen[$parent]=true;$parent=Especialidad::find($parent)?->id_especialidad_padre; }
        DB::transaction(function() use($e,$d) {$e->fill($d)->save();Audit::log('Especialidades','GUARDAR',$e->nombre);});
        return redirect()->route('admin.especialidades.index')->with('success','Especialidad guardada.');
    }
}

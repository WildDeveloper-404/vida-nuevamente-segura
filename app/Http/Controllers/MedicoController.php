<?php
namespace App\Http\Controllers;
use App\Models\{Medico,Persona,Usuario,Especialidad,Rol,CitaMedica};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\Rule;
class MedicoController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q'=>'nullable|string|max:100','especialidad'=>'nullable|integer']);
        return view('admin.medicos.index',['medicos'=>Medico::with(['persona.usuario','especialidades'])->when($r->filled('q'),fn($q)=>$q->whereHas('persona',fn($p)=>$p->where('apellidos','ilike','%'.$r->q.'%')->orWhere('nombres','ilike','%'.$r->q.'%')))->when($r->filled('especialidad'),fn($q)=>$q->whereHas('especialidades',fn($e)=>$e->where('especialidad.id_especialidad',$r->especialidad)))->paginate(20)->withQueryString(),'especialidades'=>Especialidad::where('estado','Activo')->get(),'edit'=>$r->filled('editar')?Medico::with('persona.usuario','especialidades')->findOrFail($r->integer('editar')):null]);
    }
    public function store(Request $r) { return $this->save($r,null); }
    public function update(Request $r,Medico $medico) { return $this->save($r,$medico); }
    private function save(Request $r,?Medico $m)
    {
        $r->merge(['correo'=>mb_strtolower(trim((string)$r->correo))]);
        $d=$r->validate(['ci'=>['required','string','max:20',Rule::unique('persona','ci')->ignore($m?->id_medico,'id_persona')],'nombres'=>'required|string|max:80','apellidos'=>'required|string|max:100','telefono'=>'required|string|max:20','correo'=>['required','email','max:120',Rule::unique('usuario','correo')->ignore($m?->persona->usuario?->id_usuario,'id_usuario')],'nro_matricula'=>['required','string','max:50',Rule::unique('medico','nro_matricula')->ignore($m?->id_medico,'id_medico')],'password'=>($m?'nullable':'required').'|string|min:10|max:128|confirmed','especialidades'=>'required|array|min:1','especialidades.*'=>['integer',Rule::exists('especialidad','id_especialidad')->where('estado','Activo')]]);
        DB::transaction(function() use($d,$m) {
            $p=$m?->persona??new Persona; $p->fill(collect($d)->only(['ci','nombres','apellidos','telefono'])->all())->save();
            if(!$m) $m=new Medico(['id_medico'=>$p->id_persona,'codigo_medico'=>DB::selectOne("SELECT 'MED-' || lpad(nextval('seq_codigo_medico')::text,4,'0') AS codigo")->codigo]);
            $m->nro_matricula=$d['nro_matricula'];$m->save();$m->especialidades()->sync($d['especialidades']);
            $values=['correo'=>$d['correo'],'id_rol'=>Rol::where('nombre_rol','Medico')->value('id_rol')];
            if(!empty($d['password'])) $values['password_hash']=Hash::make($d['password']);
            Usuario::updateOrCreate(['id_persona'=>$p->id_persona],$values);
            Audit::log('Medicos','GUARDAR',$m->codigo_medico);
        });
        return redirect()->route('admin.medicos.index')->with('success','Médico guardado.');
    }
    public function destroy(Medico $medico)
    {
        DB::transaction(function() use($medico) {$medico->update(['estado'=>'Inactivo']);Usuario::where('id_persona',$medico->id_medico)->update(['estado'=>'Inactivo']);Audit::log('Medicos','BAJA_LOGICA',$medico->codigo_medico);});
        $pending=CitaMedica::where('id_medico',$medico->id_medico)->whereIn('estado',['Programada','Confirmada'])->count();
        return back()->with('success',"Médico desactivado. Debe gestionar $pending citas pendientes desde recepción.");
    }
}
